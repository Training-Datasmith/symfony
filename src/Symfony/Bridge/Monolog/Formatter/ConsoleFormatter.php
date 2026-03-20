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
namespace Symfony\Bridge\Monolog\Formatter;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Level;
use Monolog\Log_Record;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Stub;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper;
/**
 * Formats incoming records for console output by coloring them depending on log level.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class Console_Formatter implements Formatter_Interface
{
    public const SIMPLE_FORMAT = "%datetime% %start_tag%%level_name%%end_tag% <comment>[%channel%]</> %message%%context%%extra%\n";
    public const SIMPLE_DATE = 'H:i:s';
    private const LEVEL_COLOR_MAP = [Level::Debug->value => 'fg=white', Level::Info->value => 'fg=green', Level::Notice->value => 'fg=blue', Level::Warning->value => 'fg=cyan', Level::Error->value => 'fg=yellow', Level::Critical->value => 'fg=red', Level::Alert->value => 'fg=red', Level::Emergency->value => 'fg=white;bg=red'];
    private array $options;
    private Var_Cloner $cloner;
    /**
     * @var resource|null
     */
    private $output_buffer;
    private Cli_Dumper $dumper;
    /**
     * Available options:
     *   * format: The format of the outputted log string. The following placeholders are supported: %datetime%, %start_tag%, %level_name%, %end_tag%, %channel%, %message%, %context%, %extra%;
     *   * date_format: The format of the outputted date string;
     *   * colors: If true, the log string contains ANSI code to add color;
     *   * multiline: If false, "context" and "extra" are dumped on one line.
     */
    public function __construct(array $options = [])
    {
        $this->options = array_replace(['format' => self::SIMPLE_FORMAT, 'date_format' => self::SIMPLE_DATE, 'colors' => true, 'multiline' => false, 'level_name_format' => '%-9s', 'ignore_empty_context_and_extra' => true], $options);
        if (class_exists(Var_Cloner::class)) {
            $this->cloner = new Var_Cloner();
            $this->cloner->add_casters(['*' => $this->cast_object(...)]);
            $this->output_buffer = fopen('php://memory', 'r+');
            if ($this->options['multiline']) {
                $output = $this->output_buffer;
            } else {
                $output = $this->echo_line(...);
            }
            $this->dumper = new Cli_Dumper($output, null, Cli_Dumper::DUMP_LIGHT_ARRAY | Cli_Dumper::DUMP_COMMA_SEPARATOR);
        }
    }
    public function format_batch(array $records): mixed
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }
        return $records;
    }
    public function format(Log_Record $record): mixed
    {
        $record = $this->replace_place_holder($record);
        if (!$this->options['ignore_empty_context_and_extra'] || $record->context) {
            $context = $record->context;
            $context = ($this->options['multiline'] ? "\n" : ' ') . $this->dump_data($context);
        } else {
            $context = '';
        }
        if (!$this->options['ignore_empty_context_and_extra'] || $record->extra) {
            $extra = $record->extra;
            $extra = ($this->options['multiline'] ? "\n" : ' ') . $this->dump_data($extra);
        } else {
            $extra = '';
        }
        return strtr($this->options['format'], ['%datetime%' => $record->datetime->format($this->options['date_format']), '%start_tag%' => \sprintf('<%s>', self::LEVEL_COLOR_MAP[$record->level->value]), '%level_name%' => \sprintf($this->options['level_name_format'], $record->level->get_name()), '%end_tag%' => '</>', '%channel%' => $record->channel, '%message%' => $this->replace_place_holder($record)->message, '%context%' => $context, '%extra%' => $extra]);
    }
    /**
     * @internal
     */
    public function echo_line(string $line, int $depth, string $indent_pad): void
    {
        if (-1 !== $depth) {
            fwrite($this->output_buffer, $line);
        }
    }
    /**
     * @internal
     */
    public function cast_object(mixed $v, array $a, Stub $s, bool $is_nested): array
    {
        if ($this->options['multiline']) {
            return $a;
        }
        if ($is_nested && !$v instanceof \DateTimeInterface) {
            $s->cut = -1;
            $a = [];
        }
        return $a;
    }
    private function replace_place_holder(Log_Record $record): Log_Record
    {
        $message = $record->message;
        if (!str_contains($message, '{')) {
            return $record;
        }
        $context = $record->context;
        $replacements = [];
        foreach ($context as $k => $v) {
            // Remove quotes added by the dumper around string.
            $v = trim($this->dump_data($v, false), '"');
            $v = Output_Formatter::escape($v);
            $replacements['{' . $k . '}'] = \sprintf('<comment>%s</>', $v);
        }
        return $record->with(message: strtr($message, $replacements));
    }
    private function dump_data(mixed $data, ?bool $colors = null): string
    {
        if (!isset($this->dumper)) {
            return '';
        }
        if (null === $colors) {
            $this->dumper->set_colors($this->options['colors']);
        } else {
            $this->dumper->set_colors($colors);
        }
        if (\is_array($data) && ($data['data'] ?? null) instanceof Data) {
            $data = $data['data'];
        } elseif (!$data instanceof Data) {
            $data = $this->cloner->clone_var($data);
        }
        $data = $data->with_ref_handles(false);
        $this->dumper->dump($data);
        $dump = stream_get_contents($this->output_buffer, -1, 0);
        rewind($this->output_buffer);
        ftruncate($this->output_buffer, 0);
        return rtrim($dump);
    }
}