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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper;
use Symfony\Component\Var_Dumper\Dumper\Context_Provider\Source_Context_Provider;
use Symfony\Component\Var_Dumper\Dumper\Data_Dumper_Interface;
use Symfony\Component\Var_Dumper\Dumper\Html_Dumper;
use Symfony\Component\Var_Dumper\Server\Connection;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class Dump_Data_Collector extends Data_Collector implements Data_Dumper_Interface
{
    private readonly string|File_Link_Formatter|false $file_link_format;
    private int $data_count = 0;
    private bool $is_collected = true;
    private int $clones_count = 0;
    private int $clones_index = 0;
    private readonly string $charset;
    private readonly mixed $source_context_provider;
    private readonly bool $web_mode;
    public function __construct(private readonly ?Stopwatch $stopwatch = null, string|File_Link_Formatter|null $file_link_format = null, ?string $charset = null, private readonly ?Request_Stack $request_stack = null, private readonly Data_Dumper_Interface|Connection|null $dumper = null, ?bool $web_mode = null)
    {
        $file_link_format = ($file_link_format ?: \ini_get('xdebug.file_link_format')) ?: get_cfg_var('xdebug.file_link_format');
        $this->file_link_format = $file_link_format instanceof File_Link_Formatter && false === $file_link_format->format('', 0) ? false : $file_link_format;
        $this->charset = (($charset ?: \ini_get('php.output_encoding')) ?: \ini_get('default_charset')) ?: 'UTF-8';
        $this->web_mode = $web_mode ?? !\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
        $this->source_context_provider = $dumper instanceof Connection && isset($dumper->get_context_providers()['source']) ? $dumper->get_context_providers()['source'] : new Source_Context_Provider($this->charset);
    }
    public function __clone()
    {
        $this->clones_index = ++$this->clones_count;
    }
    public function dump(Data $data): ?string
    {
        $this->stopwatch?->start('dump');
        ['name' => $name, 'file' => $file, 'line' => $line, 'file_excerpt' => $file_excerpt] = $this->source_context_provider->get_context();
        if (!$this->dumper || $this->dumper instanceof Connection && !$this->dumper->write($data)) {
            $this->is_collected = false;
        }
        $context = $data->get_context();
        $label = $context['label'] ?? '';
        unset($context['label']);
        $data = $data->with_context($context);
        if ($this->dumper && !$this->dumper instanceof Connection) {
            $this->do_dump($this->dumper, $data, $name, $file, $line, $label);
        }
        if (!$this->data_count) {
            $this->data = [];
        }
        $this->data[] = compact('data', 'name', 'file', 'line', 'fileExcerpt', 'label');
        ++$this->data_count;
        $this->stopwatch?->stop('dump');
        return null;
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (!$this->data_count) {
            $this->data = [];
        }
        // Sub-requests and programmatic calls stay in the collected profile.
        if ($this->dumper || $this->request_stack && $this->request_stack->get_main_request() !== $request || $request->is_xml_http_request() || $request->headers->has('Origin')) {
            return;
        }
        // In all other conditions that remove the web debug toolbar, dumps are written on the output.
        if (!$this->request_stack || !$response->headers->has('X-Debug-Token') || $response->is_redirection() || $response->headers->has('Content-Type') && !str_contains($response->headers->get('Content-Type') ?? '', 'html') || 'html' !== $request->get_request_format() || false === strripos($response->get_content(), '</body>')) {
            if ($response->headers->has('Content-Type') && str_contains($response->headers->get('Content-Type') ?? '', 'html')) {
                $dumper = new Html_Dumper('php://output', $this->charset);
            } else {
                $dumper = new Cli_Dumper('php://output', $this->charset);
            }
            $dumper->set_display_options(['fileLinkFormat' => $this->file_link_format]);
            foreach ($this->data as $dump) {
                $this->do_dump($dumper, $dump['data'], $dump['name'], $dump['file'], $dump['line'], $dump['label'] ?? '');
            }
        }
    }
    public function reset(): void
    {
        $this->stopwatch?->reset();
        parent::reset();
        $this->data_count = 0;
        $this->is_collected = true;
        $this->clones_count = 0;
        $this->clones_index = 0;
    }
    public function __serialize(): array
    {
        if (!$this->data_count) {
            $this->data = [];
        }
        if ($this->clones_count !== $this->clones_index) {
            return [];
        }
        $this->data[] = $this->file_link_format;
        $this->data[] = $this->charset;
        $this->data_count = 0;
        $this->is_collected = true;
        return ['data' => $this->data];
    }
    public function __unserialize(array $data): void
    {
        $this->data = array_pop($data) ?? [];
        $charset = array_pop($this->data);
        $file_link_format = array_pop($this->data);
        $this->data_count = \count($this->data);
        foreach ($this->data as $dump) {
            if (!\is_string($dump['name']) || !\is_string($dump['file']) || !\is_int($dump['line'])) {
                throw new \BadMethodCallException('Cannot unserialize ' . self::class);
            }
        }
        self::__construct($this->stopwatch ?? null, \is_string($file_link_format) || $file_link_format instanceof File_Link_Formatter ? $file_link_format : null, \is_string($charset) ? $charset : null);
    }
    public function get_dumps_count(): int
    {
        return $this->data_count;
    }
    public function get_dumps(string $format, int $max_depth_limit = -1, int $max_items_per_depth = -1): array
    {
        $data = fopen('php://memory', 'r+');
        if ('html' === $format) {
            $dumper = new Html_Dumper($data, $this->charset);
            $dumper->set_display_options(['fileLinkFormat' => $this->file_link_format]);
        } else {
            throw new \InvalidArgumentException(\sprintf('Invalid dump format: "%s".', $format));
        }
        $dumps = [];
        if (!$this->data_count) {
            return $this->data = [];
        }
        foreach ($this->data as $dump) {
            $dumper->dump($dump['data']->with_max_depth($max_depth_limit)->with_max_items_per_depth($max_items_per_depth));
            $dump['data'] = stream_get_contents($data, -1, 0);
            ftruncate($data, 0);
            rewind($data);
            $dumps[] = $dump;
        }
        return $dumps;
    }
    public function get_name(): string
    {
        return 'dump';
    }
    public function __destruct()
    {
        if (0 === $this->clones_count-- && !$this->is_collected && $this->data_count) {
            $this->clones_count = 0;
            $this->is_collected = true;
            $h = headers_list();
            $i = \count($h);
            array_unshift($h, 'Content-Type: ' . \ini_get('default_mimetype'));
            while (0 !== stripos($h[$i], 'Content-Type:')) {
                --$i;
            }
            if ($this->web_mode) {
                $dumper = new Html_Dumper('php://output', $this->charset);
            } else {
                $dumper = new Cli_Dumper('php://output', $this->charset);
            }
            $dumper->set_display_options(['fileLinkFormat' => $this->file_link_format]);
            foreach ($this->data as $i => $dump) {
                $this->data[$i] = null;
                $this->do_dump($dumper, $dump['data'], $dump['name'], $dump['file'], $dump['line'], $dump['label'] ?? '');
            }
            $this->data = [];
            $this->data_count = 0;
        }
    }
    private function do_dump(Data_Dumper_Interface $dumper, Data $data, string $name, string $file, int $line, string $label): void
    {
        if ($dumper instanceof Cli_Dumper) {
            $context_dumper = function ($name, $file, $line, $fmt, $label): void {
                $this->line = '' !== $label ? $this->style('meta', $label) . ' in ' : '';
                if ($this instanceof Html_Dumper) {
                    if ($file) {
                        $s = $this->style('meta', '%s');
                        $f = strip_tags($this->style('', $file));
                        $name = strip_tags($this->style('', $name));
                        if ($fmt && $link = \is_string($fmt) ? strtr($fmt, ['%f' => $file, '%l' => $line]) : $fmt->format($file, $line)) {
                            $name = \sprintf('<a href="%s" title="%s">' . $s . '</a>', strip_tags($this->style('', $link)), $f, $name);
                        } else {
                            $name = \sprintf('<abbr title="%s">' . $s . '</abbr>', $f, $name);
                        }
                    } else {
                        $name = $this->style('meta', $name);
                    }
                    $this->line .= $name . ' on line ' . $this->style('meta', $line) . ':';
                } else {
                    $this->line .= $this->style('meta', $name) . ' on line ' . $this->style('meta', $line) . ':';
                }
                $this->dump_line(0);
            };
            $context_dumper = $context_dumper->bind_to($dumper, $dumper);
            $context_dumper($name, $file, $line, $this->file_link_format, $label);
        } else {
            $cloner = new Var_Cloner();
            $dumper->dump($cloner->clone_var(('' !== $label ? $label . ' in ' : '') . $name . ' on line ' . $line . ':'));
        }
        $dumper->dump($data);
    }
}