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
namespace Symfony\Bridge\Php_Unit\Deprecation_Error_Handler;

/**
 * @internal
 */
class Configuration
{
    /**
     * @var int[]
     */
    private ?array $thresholds = null;
    private bool $enabled = true;
    /**
     * @var bool[]
     */
    private array $verbose_output;
    /**
     * @var string[]
     */
    private array $ignore_deprecation_patterns = [];
    private readonly bool $generate_baseline;
    private readonly string $baseline_file;
    private array $baseline_deprecations = [];
    /**
     * @param int[]       $thresholds       A hash associating groups to thresholds
     * @param string      $regex            Will be matched against messages, to decide whether to display a stack trace
     * @param bool[]      $verboseOutput    Keyed by groups
     * @param string      $ignoreFile       The path to the ignore deprecation patterns file
     * @param bool        $generateBaseline Whether to generate or update the baseline file
     * @param string      $baselineFile     The path to the baseline file
     * @param string|null $logFile          The path to the log file
     */
    private function __construct(array $thresholds = [], private readonly string $regex = '', array $verbose_output = [], string $ignore_file = '', bool $generate_baseline = false, string $baseline_file = '', private readonly ?string $log_file = null)
    {
        $groups = ['total', 'indirect', 'direct', 'self'];
        foreach ($thresholds as $group => $threshold) {
            if (!\in_array($group, $groups, true)) {
                throw new \InvalidArgumentException(\sprintf('Unrecognized threshold "%s", expected one of "%s".', $group, implode('", "', $groups)));
            }
            if (!is_numeric($threshold)) {
                throw new \InvalidArgumentException(\sprintf('Threshold for group "%s" has invalid value "%s".', $group, $threshold));
            }
            $this->thresholds[$group] = (int) $threshold;
        }
        if (isset($this->thresholds['direct'])) {
            $this->thresholds += ['self' => $this->thresholds['direct']];
        }
        if (isset($this->thresholds['indirect'])) {
            $this->thresholds += ['direct' => $this->thresholds['indirect'], 'self' => $this->thresholds['indirect']];
        }
        foreach ($groups as $group) {
            if (!isset($this->thresholds[$group])) {
                $this->thresholds[$group] = $this->thresholds['total'] ?? 999999;
            }
        }
        $this->verbose_output = ['unsilenced' => true, 'direct' => true, 'indirect' => true, 'self' => true, 'other' => true];
        foreach ($verbose_output as $group => $status) {
            if (!isset($this->verbose_output[$group])) {
                throw new \InvalidArgumentException(\sprintf('Unsupported verbosity group "%s", expected one of "%s".', $group, implode('", "', array_keys($this->verbose_output))));
            }
            $this->verbose_output[$group] = $status;
        }
        if ($ignore_file) {
            if (!is_file($ignore_file)) {
                throw new \InvalidArgumentException(\sprintf('The ignoreFile "%s" does not exist.', $ignore_file));
            }
            set_error_handler(static function ($t, $m) use ($ignore_file, &$line): void {
                throw new \RuntimeException(\sprintf('Invalid pattern found in "%s" on line "%d"', $ignore_file, 1 + $line) . substr($m, 12));
            });
            try {
                foreach (file($ignore_file) as $pattern) {
                    if ('#' !== (trim($pattern)[0] ?? '#')) {
                        preg_match($pattern, '');
                        $this->ignore_deprecation_patterns[] = $pattern;
                    }
                }
            } finally {
                restore_error_handler();
            }
        }
        if ($generate_baseline && !$baseline_file) {
            throw new \InvalidArgumentException('You cannot use the "generateBaseline" configuration option without providing a "baselineFile" configuration option.');
        }
        $this->generate_baseline = $generate_baseline;
        $this->baseline_file = $baseline_file;
        if ($this->baseline_file && !$this->generate_baseline) {
            if (is_file($this->baseline_file)) {
                $map = json_decode(file_get_contents($this->baseline_file));
                foreach ($map as $baseline_deprecation) {
                    $this->baseline_deprecations[$baseline_deprecation->location][$baseline_deprecation->message] = $baseline_deprecation->count;
                }
            } else {
                throw new \InvalidArgumentException(\sprintf('The baselineFile "%s" does not exist.', $this->baseline_file));
            }
        }
    }
    public function is_enabled(): bool
    {
        return $this->enabled;
    }
    /**
     * @param DeprecationGroup[] $deprecationGroups
     */
    public function tolerates(array $deprecation_groups): bool
    {
        $grand_total = 0;
        foreach ($deprecation_groups as $name => $group) {
            if ('legacy' !== $name) {
                $grand_total += $group->count();
            }
        }
        if ($grand_total > $this->thresholds['total']) {
            return false;
        }
        foreach (['self', 'direct', 'indirect'] as $deprecation_type) {
            if ($deprecation_groups[$deprecation_type]->count() > $this->thresholds[$deprecation_type]) {
                return false;
            }
        }
        return true;
    }
    public function is_ignored_deprecation(Deprecation $deprecation): bool
    {
        if (!$this->ignore_deprecation_patterns) {
            return false;
        }
        $result = @preg_filter($this->ignore_deprecation_patterns, '$0', $deprecation->get_message());
        if (\PREG_NO_ERROR !== preg_last_error()) {
            throw new \RuntimeException(preg_last_error_msg());
        }
        return (bool) $result;
    }
    /**
     * @param array<string,DeprecationGroup> $deprecationGroups
     *
     * @return bool true if the threshold is not reached for the deprecation type nor for the total
     */
    public function tolerates_for_group(string $group_name, array $deprecation_groups): bool
    {
        $grand_total = 0;
        foreach ($deprecation_groups as $type => $group) {
            if ('legacy' !== $type) {
                $grand_total += $group->count();
            }
        }
        if ($grand_total > $this->thresholds['total']) {
            return false;
        }
        if (\in_array($group_name, ['self', 'direct', 'indirect'], true) && $deprecation_groups[$group_name]->count() > $this->thresholds[$group_name]) {
            return false;
        }
        return true;
    }
    public function is_baseline_deprecation(Deprecation $deprecation): bool
    {
        if ($deprecation->is_legacy()) {
            return false;
        }
        if ($deprecation->originates_from_debug_class_loader()) {
            $location = $deprecation->triggering_class();
        } elseif ($deprecation->originates_from_an_object()) {
            $location = $deprecation->originating_class() . '::' . $deprecation->originating_method();
        } else {
            $location = 'procedural code';
        }
        $message = $deprecation->get_message();
        $result = isset($this->baseline_deprecations[$location][$message]) && $this->baseline_deprecations[$location][$message] > 0;
        if ($this->generate_baseline) {
            if ($result) {
                ++$this->baseline_deprecations[$location][$message];
            } else {
                $this->baseline_deprecations[$location][$message] = 1;
                $result = true;
            }
        } elseif ($result) {
            --$this->baseline_deprecations[$location][$message];
        }
        return $result;
    }
    public function is_generating_baseline(): bool
    {
        return $this->generate_baseline;
    }
    public function get_baseline_file(): string
    {
        return $this->baseline_file;
    }
    public function write_baseline(): void
    {
        $map = [];
        foreach ($this->baseline_deprecations as $location => $messages) {
            foreach ($messages as $message => $count) {
                $map[] = ['location' => $location, 'message' => $message, 'count' => $count];
            }
        }
        file_put_contents($this->baseline_file, json_encode($map, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
    public function should_display_stack_trace(string $message): bool
    {
        return '' !== $this->regex && preg_match($this->regex, $message);
    }
    public function is_in_regex_mode(): bool
    {
        return '' !== $this->regex;
    }
    public function verbose_output($group): bool
    {
        return $this->verbose_output[$group];
    }
    public function should_write_to_log_file(): bool
    {
        return null !== $this->log_file;
    }
    public function get_log_file(): ?string
    {
        return $this->log_file;
    }
    /**
     * @param string $serializedConfiguration An encoded string, for instance max[total]=1234&max[indirect]=42
     */
    public static function from_url_encoded_string(string $serialized_configuration): self
    {
        parse_str($serialized_configuration, $normalized_configuration);
        foreach (array_keys($normalized_configuration) as $key) {
            if (!\in_array($key, ['max', 'disabled', 'verbose', 'quiet', 'ignoreFile', 'generateBaseline', 'baselineFile', 'logFile'], true)) {
                throw new \InvalidArgumentException(\sprintf('Unknown configuration option "%s".', $key));
            }
        }
        $normalized_configuration += ['max' => ['total' => 0], 'disabled' => false, 'verbose' => true, 'quiet' => [], 'ignoreFile' => '', 'generateBaseline' => false, 'baselineFile' => '', 'logFile' => null];
        if ('' === $normalized_configuration['disabled'] || filter_var($normalized_configuration['disabled'], \FILTER_VALIDATE_BOOLEAN)) {
            return self::in_disabled_mode();
        }
        $verbose_output = [];
        foreach (['unsilenced', 'direct', 'indirect', 'self', 'other'] as $group) {
            $verbose_output[$group] = filter_var($normalized_configuration['verbose'], \FILTER_VALIDATE_BOOLEAN);
        }
        if (\is_array($normalized_configuration['quiet'])) {
            foreach ($normalized_configuration['quiet'] as $shushed_group) {
                $verbose_output[$shushed_group] = false;
            }
        }
        return new self($normalized_configuration['max'], '', $verbose_output, $normalized_configuration['ignoreFile'], filter_var($normalized_configuration['generateBaseline'], \FILTER_VALIDATE_BOOLEAN), $normalized_configuration['baselineFile'], $normalized_configuration['logFile']);
    }
    public static function in_disabled_mode(): self
    {
        $configuration = new self();
        $configuration->enabled = false;
        return $configuration;
    }
    public static function in_strict_mode(): self
    {
        return new self(['total' => 0]);
    }
    public static function in_weak_mode(): self
    {
        $verbose_output = [];
        foreach (['unsilenced', 'direct', 'indirect', 'self', 'other'] as $group) {
            $verbose_output[$group] = false;
        }
        return new self([], '', $verbose_output);
    }
    public static function from_number($upper_bound): self
    {
        return new self(['total' => $upper_bound]);
    }
    public static function from_regex($regex): self
    {
        return new self([], $regex);
    }
}