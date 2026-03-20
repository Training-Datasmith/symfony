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
namespace Symfony\Component\Http_Kernel\Cache_Warmer;

use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Aggregates several cache warmers into a single one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Cache_Warmer_Aggregate implements Cache_Warmer_Interface
{
    private bool $optionals_enabled = false;
    private bool $only_optionals_enabled = false;
    /**
     * @param iterable<mixed, CacheWarmerInterface> $warmers
     */
    public function __construct(private readonly iterable $warmers = [], private readonly bool $debug = false, private readonly ?string $deprecation_logs_filepath = null)
    {
    }
    public function enable_optional_warmers(): void
    {
        $this->optionals_enabled = true;
    }
    public function enable_only_optional_warmers(): void
    {
        $this->only_optionals_enabled = $this->optionals_enabled = true;
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null, ?Symfony_Style $io = null): array
    {
        if ($collect_deprecations = $this->debug && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
            $collected_logs = [];
            $previous_handler = set_error_handler(static function ($type, $message, $file, $line) use (&$collected_logs, &$previous_handler) {
                if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type) {
                    return $previous_handler ? $previous_handler($type, $message, $file, $line) : false;
                }
                if (isset($collected_logs[$message])) {
                    ++$collected_logs[$message]['count'];
                    return null;
                }
                $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                // Clean the trace by removing first frames added by the error handler itself.
                for ($i = 0; isset($backtrace[$i]); ++$i) {
                    if (isset($backtrace[$i]['file'], $backtrace[$i]['line']) && $backtrace[$i]['line'] === $line && $backtrace[$i]['file'] === $file) {
                        $backtrace = \array_slice($backtrace, 1 + $i);
                        break;
                    }
                }
                $collected_logs[$message] = ['type' => $type, 'message' => $message, 'file' => $file, 'line' => $line, 'trace' => $backtrace, 'count' => 1];
                return null;
            });
        }
        $preload = [];
        try {
            foreach ($this->warmers as $warmer) {
                if (!$this->optionals_enabled && $warmer->is_optional()) {
                    continue;
                }
                if ($this->only_optionals_enabled && !$warmer->is_optional()) {
                    continue;
                }
                $start = microtime(true);
                foreach ($warmer->warm_up($cache_dir, $build_dir) as $item) {
                    if (is_dir($item) || str_starts_with($item, \dirname($cache_dir)) && !is_file($item) || $build_dir && str_starts_with($item, \dirname($build_dir)) && !is_file($item)) {
                        throw new \LogicException(\sprintf('"%s::warmUp()" should return a list of files or classes but "%s" is none of them.', $warmer::class, $item));
                    }
                    $preload[] = $item;
                }
                if ($io?->is_debug()) {
                    $io->info(\sprintf('"%s" completed in %0.2fms.', $warmer::class, 1000 * (microtime(true) - $start)));
                }
            }
        } finally {
            if ($collect_deprecations) {
                restore_error_handler();
                if (is_file($this->deprecation_logs_filepath)) {
                    $previous_logs = unserialize(file_get_contents($this->deprecation_logs_filepath));
                    if (\is_array($previous_logs)) {
                        $collected_logs = array_merge($previous_logs, $collected_logs);
                    }
                }
                file_put_contents($this->deprecation_logs_filepath, serialize(array_values($collected_logs)));
            }
        }
        return array_values(array_unique($preload));
    }
    public function is_optional(): bool
    {
        return false;
    }
}