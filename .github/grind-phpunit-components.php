<?php

declare(strict_types=1);

/**
 * Low-memory unit test grind for cloud VMs and long CI-style runs.
 *
 * Runs one PHPUnit process per directory that has phpunit.xml.dist (same slices as
 * GitHub Actions unit-tests, excluding tty/benchmark/intl-data/integration/transient).
 *
 * Do NOT run a single "./phpunit src/Symfony" — that loads ~50k tests in one process
 * and is commonly killed with OOM (exit 137) on 7–8 GiB agents.
 *
 * From repository root (after composer install + ./phpunit install):
 *
 *   php .github/grind-phpunit-components.php
 *   php .github/grind-phpunit-components.php --junit phpunit-remote-YYYYMMDD.xml
 *   php .github/grind-phpunit-components.php --offset 0 --limit 45
 *   php .github/grind-phpunit-components.php --filter Component/Form
 *   php .github/grind-phpunit-components.php --list
 *
 * Shard example (three cloud runs, merge JUnit offline or keep part files):
 *   php .github/grind-phpunit-components.php --offset 0 --limit 61 --no-merge
 *   php .github/grind-phpunit-components.php --offset 61 --limit 61 --no-merge
 *   php .github/grind-phpunit-components.php --offset 122 --no-merge
 */

$repoRoot = dirname(__DIR__);
chdir($repoRoot);

/** @var list<string> */
const GRIND_EXCLUDE_GROUPS = ['tty', 'benchmark', 'intl-data', 'integration', 'transient'];

$options = getopt('', [
    'junit:',
    'offset::',
    'limit::',
    'memory::',
    'parts-dir::',
    'filter::',
    'list',
    'no-merge',
    'keep-parts',
]);

if (isset($options['list'])) {
    foreach (grind_discover_suites($repoRoot) as $i => $dir) {
        fwrite(STDOUT, sprintf("%4d  %s\n", $i, grind_rel_path($repoRoot, $dir)));
    }
    exit(0);
}

$junitOut = grind_abs_path($repoRoot, $options['junit'] ?? 'build/phpunit-monorepo.xml');
$offset = max(0, (int) ($options['offset'] ?? 0));
$limit = isset($options['limit']) ? max(0, (int) $options['limit']) : null;
$memoryLimit = $options['memory'] ?? '512M';
$partsDir = grind_abs_path($repoRoot, $options['parts-dir'] ?? 'build/junit-parts');
$pathFilter = isset($options['filter']) ? (string) $options['filter'] : '';
$noMerge = isset($options['no-merge']);
$keepParts = isset($options['keep-parts']);

$phpunit = realpath($repoRoot . '/phpunit');
if ($phpunit === false) {
    fwrite(STDERR, "Missing phpunit at repo root — run: composer install && ./phpunit install\n");
    exit(2);
}

$suites = grind_discover_suites($repoRoot);
if ($pathFilter !== '') {
    $suites = array_values(array_filter(
        $suites,
        static fn (string $dir): bool => str_contains(grind_rel_path($repoRoot, $dir), $pathFilter)
    ));
}
if ($offset > 0) {
    $suites = array_slice($suites, $offset);
}
if ($limit !== null && $limit > 0) {
    $suites = array_slice($suites, 0, $limit);
}

if ($suites === []) {
    fwrite(STDERR, "No suites to run (check --offset/--limit/--filter).\n");
    exit(2);
}

if (!is_dir($partsDir)) {
    mkdir($partsDir, 0777, true);
}

$writer = null;
if (!$noMerge) {
    $writer = new XMLWriter();
    $junitDir = dirname($junitOut);
    if (!is_dir($junitDir)) {
        mkdir($junitDir, 0777, true);
    }
    $writer->openUri($junitOut);
    $writer->startDocument('1.0', 'UTF-8');
    $writer->startElement('testsuites');
}

$worstExit = 0;
$total = count($suites);
$mergedCases = 0;

fwrite(STDERR, sprintf(
    "grind: %d suite(s), memory_limit=%s per process, merge=%s\n",
    $total,
    $memoryLimit,
    $noMerge ? 'off' : $junitOut
));

foreach ($suites as $i => $suiteDir) {
    $rel = grind_rel_path($repoRoot, $suiteDir);
    $slug = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $rel) ?? 'suite', '-');
    $partJUnit = $partsDir . '/' . $slug . '.xml';

    $cmd = [
        PHP_BINARY,
        '-d',
        'memory_limit=' . $memoryLimit,
        $phpunit,
    ];
    foreach (GRIND_EXCLUDE_GROUPS as $group) {
        $cmd[] = '--exclude-group';
        $cmd[] = $group;
    }
    $cmd[] = '--log-junit';
    $cmd[] = $partJUnit;
    $cmd[] = $rel;

    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes, $repoRoot);
    if (!is_resource($proc)) {
        fwrite(STDERR, sprintf("[%d/%d] failed to start: %s\n", $i + 1, $total, $rel));
        $worstExit = max($worstExit, 1);
        continue;
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    $worstExit = max($worstExit, $exit);

    $caseCount = 0;
    if (is_file($partJUnit)) {
        $caseCount = grind_count_testcases($partJUnit);
        if ($writer instanceof XMLWriter) {
            grind_stream_testcases($writer, $partJUnit, $mergedCases);
        }
        if (!$keepParts && $writer instanceof XMLWriter) {
            @unlink($partJUnit);
        }
    }

    fwrite(STDERR, sprintf(
        "[%d/%d] %s exit=%d tests=%d\n",
        $i + 1,
        $total,
        $rel,
        $exit,
        $caseCount
    ));

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

if ($writer instanceof XMLWriter) {
    $writer->endElement();
    $writer->endDocument();
    $writer->flush();
    fwrite(STDERR, sprintf("Wrote merged JUnit: %s (%d testcase nodes)\n", $junitOut, $mergedCases));
} elseif ($noMerge) {
    fwrite(STDERR, "Per-suite JUnit under: $partsDir\n");
}

exit($worstExit);

/**
 * @return list<string> absolute suite directories
 */
function grind_discover_suites(string $repoRoot): array
{
    $base = $repoRoot . '/src/Symfony';
    if (!is_dir($base)) {
        return [];
    }

    $suites = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'phpunit.xml.dist') {
            $suites[] = $file->getPath();
        }
    }
    sort($suites);

    return $suites;
}

function grind_abs_path(string $repoRoot, string $path): string
{
    if ($path === '' || $path[0] === '/' || preg_match('#^[A-Za-z]:[/\\\\]#', $path)) {
        return $path;
    }

    return $repoRoot . '/' . str_replace('\\', '/', $path);
}

function grind_rel_path(string $repoRoot, string $absoluteDir): string
{
    $root = str_replace('\\', '/', realpath($repoRoot) ?: $repoRoot);
    $path = str_replace('\\', '/', realpath($absoluteDir) ?: $absoluteDir);
    if (str_starts_with($path, $root . '/')) {
        return substr($path, strlen($root) + 1);
    }

    return $path;
}

function grind_count_testcases(string $partPath): int
{
    $count = 0;
    $handle = fopen($partPath, 'rb');
    if ($handle === false) {
        return 0;
    }
    while (!feof($handle)) {
        $chunk = fread($handle, 2_000_000);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $count += substr_count($chunk, '<testcase ');
    }
    fclose($handle);

    return $count;
}

function grind_stream_testcases(XMLWriter $writer, string $partPath, int &$mergedCases): void
{
    $reader = new XMLReader();
    if (@$reader->open($partPath) === false) {
        return;
    }

    while (@$reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'testcase') {
            $writer->writeRaw($reader->readOuterXML());
            ++$mergedCases;
        }
    }
    $reader->close();
}
