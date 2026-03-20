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
namespace Symfony\Bridge\Twig\Data_Collector;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Late_Data_Collector_Interface;
use Twig\Environment;
use Twig\Error\Loader_Error;
use Twig\Markup;
use Twig\Profiler\Dumper\Html_Dumper;
use Twig\Profiler\Profile;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Twig_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    private array $computed;
    public function __construct(private Profile $profile, private readonly ?Environment $twig = null)
    {
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }
    public function reset(): void
    {
        $this->profile->reset();
        unset($this->computed);
        $this->data = [];
    }
    public function late_collect(): void
    {
        $this->data['profile'] = serialize($this->profile);
        $this->data['template_paths'] = [];
        if (null === $this->twig) {
            return;
        }
        $template_finder = function (Profile $profile) use (&$template_finder): void {
            if ($profile->is_template()) {
                try {
                    $template = $this->twig->load($name = $profile->get_name());
                } catch (Loader_Error) {
                    $template = null;
                }
                if (null !== $template && '' !== $path = $template->get_source_context()->get_path()) {
                    $this->data['template_paths'][$name] = $path;
                }
            }
            foreach ($profile as $p) {
                $template_finder($p);
            }
        };
        $template_finder($this->profile);
    }
    public function get_time(): float
    {
        return $this->get_profile()->get_duration() * 1000;
    }
    public function get_template_count(): int
    {
        return $this->get_computed_data('template_count');
    }
    public function get_template_paths(): array
    {
        return $this->data['template_paths'];
    }
    public function get_templates(): array
    {
        return $this->get_computed_data('templates');
    }
    public function get_block_count(): int
    {
        return $this->get_computed_data('block_count');
    }
    public function get_macro_count(): int
    {
        return $this->get_computed_data('macro_count');
    }
    public function get_html_call_graph(): Markup
    {
        $dumper = new Html_Dumper();
        $dump = $dumper->dump($this->get_profile());
        // needed to remove the hardcoded CSS styles
        $dump = str_replace(['<span style="background-color: #ffd">', '<span style="color: #d44">', '<span style="background-color: #dfd">', '<span style="background-color: #ddf">'], ['<span class="status-warning">', '<span class="status-error">', '<span class="status-success">', '<span class="status-info">'], $dump);
        return new Markup($dump, 'UTF-8');
    }
    public function get_profile(): Profile
    {
        return $this->profile ??= unserialize($this->data['profile'], ['allowed_classes' => [Profile::class]]);
    }
    private function get_computed_data(string $index): mixed
    {
        $this->computed ??= $this->compute_data($this->get_profile());
        return $this->computed[$index];
    }
    private function compute_data(Profile $profile): array
    {
        $data = ['template_count' => 0, 'block_count' => 0, 'macro_count' => 0];
        $templates = [];
        foreach ($profile as $p) {
            $d = $this->compute_data($p);
            $data['template_count'] += ($p->is_template() ? 1 : 0) + $d['template_count'];
            $data['block_count'] += ($p->is_block() ? 1 : 0) + $d['block_count'];
            $data['macro_count'] += ($p->is_macro() ? 1 : 0) + $d['macro_count'];
            if ($p->is_template()) {
                if (!isset($templates[$p->get_template()])) {
                    $templates[$p->get_template()] = 1;
                } else {
                    ++$templates[$p->get_template()];
                }
            }
            foreach ($d['templates'] as $template => $count) {
                if (!isset($templates[$template])) {
                    $templates[$template] = $count;
                } else {
                    $templates[$template] += $count;
                }
            }
        }
        $data['templates'] = $templates;
        return $data;
    }
    public function get_name(): string
    {
        return 'twig';
    }
}