#!/usr/bin/env php
<?php 
declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
require __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Var_Exporter\Var_Exporter;
Builder::clean_target();
$emojis_code_points = Builder::get_emojis_code_points();
Builder::save_rules(Builder::build_rules($emojis_code_points));
Builder::save_rules(Builder::build_strip_rules($emojis_code_points));
$emoji_maps = ['slack', 'github', 'gitlab'];
foreach ($emoji_maps as $map) {
    $maps = Builder::{"build{$map}Maps"}($emojis_code_points);
    Builder::save_rules(array_combine(["emoji-{$map}", "{$map}-emoji"], Builder::create_rules($maps, true)));
}
Builder::save_rules(Builder::build_text_rules($emojis_code_points, $emoji_maps));
final class Builder
{
    private const TARGET_DIR = __DIR__ . '/../data/';
    public static function get_emojis_code_points(): array
    {
        $lines = file(__DIR__ . '/vendor/emoji-test.txt');
        $emojis_code_points = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }
            if (str_starts_with($line, '#')) {
                continue;
            }
            // 263A FE0F    ; fully-qualified     # ☺️ E0.6 smiling face
            preg_match('{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu', $line, $matches);
            if (!$matches) {
                throw new DomainException("Could not parse line: \"{$line}\".");
            }
            $code_points = str_replace(' ', '-', trim($matches['codePoints']));
            $emojis_code_points[$code_points] = $matches['emoji'];
            // We also add a version without the "Zero Width Joiner"
            $code_points = str_replace('-200D-', '-', $code_points);
            $emojis_code_points[$code_points] = $matches['emoji'];
        }
        return $emojis_code_points;
    }
    public static function build_rules(array $emojis_code_points): Generator
    {
        $filesystem = new Filesystem();
        $files = (new Finder())->files()->in([__DIR__ . '/vendor/unicode-org/cldr/common/annotationsDerived', __DIR__ . '/vendor/unicode-org/cldr/common/annotations'])->name('*.xml');
        $maps_by_locale = [];
        foreach ($files as $file) {
            $locale = $file->get_basename('.xml');
            $maps_by_locale[$locale] ??= [];
            $document = new Dom_Document();
            $document->load_xml($filesystem->read_file($file));
            $xpath = new Domx_Path($document);
            $results = $xpath->query('.//annotation[@type="tts"]');
            foreach ($results as $result) {
                $emoji = $result->get_attribute('cp');
                $name = $result->text_content;
                // Ignoring the hierarchical metadata instructions
                // (real value will be filled by the parent locale)
                if (str_contains($name, '↑↑')) {
                    continue;
                }
                $parts = preg_split('//u', (string) $emoji, -1, \PREG_SPLIT_NO_EMPTY);
                $emoji_code_points = strtoupper(implode('-', array_map(dechex(...), array_map(mb_ord(...), $parts))));
                if (!array_key_exists($emoji_code_points, $emojis_code_points)) {
                    continue;
                }
                $code_points_count = mb_strlen((string) $emoji);
                $maps_by_locale[$locale][$code_points_count][$emoji] = $name;
            }
        }
        ksort($maps_by_locale);
        foreach ($maps_by_locale as $locale => $locale_maps) {
            $parent_locale = $locale;
            while (false !== $i = strrpos($parent_locale, '_')) {
                $parent_locale = substr($parent_locale, 0, $i);
                $parent_maps = $maps_by_locale[$parent_locale] ?? [];
                foreach ($parent_maps as $code_points_count => $parent_map) {
                    // Ensuring the result map contains all the emojis from the parent map
                    // if not already defined by the current locale
                    $locale_maps[$code_points_count] = [...$parent_map, ...$locale_maps[$code_points_count] ?? []];
                }
            }
            // Skip locales without any emoji
            if ($locale_rules = self::create_rules($locale_maps)) {
                yield strtolower("emoji-{$locale}") => $locale_rules;
            }
        }
    }
    public static function build_git_hub_maps(array $emojis_code_points): array
    {
        $emojis = json_decode((new Filesystem())->read_file(__DIR__ . '/vendor/github-emojis.json'), true, flags: JSON_THROW_ON_ERROR);
        $maps = [];
        foreach ($emojis as $short_code => $url) {
            $emoji_code_points = strtoupper(basename(parse_url((string) $url, \PHP_URL_PATH), '.png'));
            if (!array_key_exists($emoji_code_points, $emojis_code_points)) {
                continue;
            }
            $emoji = $emojis_code_points[$emoji_code_points];
            $emoji_priority = mb_strlen((string) $emoji) << 1;
            $maps[$emoji_priority + 1][":{$short_code}:"] = $emoji;
        }
        return $maps;
    }
    public static function build_gitlab_maps(): array
    {
        $emojis = json_decode((new Filesystem())->read_file(__DIR__ . '/vendor/gitlab-emojis.json'), true, flags: JSON_THROW_ON_ERROR);
        $maps = [];
        foreach ($emojis as $short_name => $emoji_item) {
            $emoji = $emoji_item['moji'];
            $emoji_priority = mb_strlen((string) $emoji) << 1;
            $maps[$emoji_priority + 1][":{$short_name}:"] = $emoji;
        }
        return $maps;
    }
    public static function build_slack_maps(array $emojis_code_points): array
    {
        $emojis = json_decode((new Filesystem())->read_file(__DIR__ . '/vendor/slack-emojis.json'), true, flags: JSON_THROW_ON_ERROR);
        $maps = [];
        foreach ($emojis as $data) {
            $emoji = $emojis_code_points[$data['unified']];
            $emoji_priority = mb_strlen((string) $emoji) << 1;
            $maps[$emoji_priority + 1][":{$data['short_name']}:"] = $emoji;
            foreach ($data['short_names'] as $short_name) {
                $maps[$emoji_priority][":{$short_name}:"] = $emoji;
            }
        }
        return $maps;
    }
    public static function build_text_rules(array $emoji_code_points, array $locales): iterable
    {
        $maps = [];
        foreach ($locales as $locale) {
            foreach (self::{"build{$locale}Maps"}($emoji_code_points) as $emoji_priority => $map) {
                foreach ($map as $text => $emoji) {
                    $maps[$emoji_priority][str_replace('_', '-', $text)] ??= $emoji;
                }
            }
        }
        [$map, $reverse] = self::create_rules($maps, true);
        return ['emoji-text' => $map, 'text-emoji' => $reverse];
    }
    public static function build_strip_rules(array $emojis_code_points): iterable
    {
        $maps = [];
        foreach ($emojis_code_points as $emoji) {
            $maps[mb_strlen((string) $emoji)][$emoji] = '';
        }
        return ['emoji-strip' => self::create_rules($maps)];
    }
    public static function clean_target(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::TARGET_DIR);
        $fs->mkdir(self::TARGET_DIR);
    }
    public static function save_rules(iterable $rules_by_locale): void
    {
        $fs = new Filesystem();
        $first_chars = [];
        foreach ($rules_by_locale as $filename => $rules) {
            $fs->dump_file(self::TARGET_DIR . "/{$filename}.php", "<?php\n\nreturn " . Var_Exporter::export($rules) . ";\n");
            foreach ($rules as $k => $v) {
                if (!str_starts_with((string) $filename, 'emoji-')) {
                    continue;
                }
                $c = $k[$j] ?? $k[$i];
                $first_chars[$c] = $c;
            }
        }
        sort($first_chars);
        $quick_check = '"' . str_replace('%', '\x', rawurlencode(implode('', $first_chars))) . '"';
        $file = dirname(__DIR__, 2) . '/EmojiTransliterator.php';
        $fs->dump_file($file, preg_replace('/QUICK_CHECK = .*;/m', "QUICK_CHECK = {$quick_check};", $fs->read_file($file)));
    }
    public static function create_rules(array $maps, bool $reverse = false): array
    {
        // We must sort the maps by the number of code points, because the order really matters:
        // 🫶🏼 must be before 🫶
        krsort($maps);
        if (!$reverse) {
            return array_merge(...$maps);
        }
        $emoji_text = $text_emoji = [];
        foreach ($maps as $map) {
            uksort($map, static fn($a, $b): int => strnatcmp(substr((string) $a, 1, -1), substr((string) $b, 1, -1)));
            $text_emoji = array_merge($map, $text_emoji);
            $map = array_flip($map);
            $emoji_text += $map;
        }
        return [$emoji_text, $text_emoji];
    }
}