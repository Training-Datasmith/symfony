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
namespace Symfony\Bundle\Web_Profiler_Bundle\Twig;

use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Dumper\Html_Dumper;
use Twig\Environment;
use Twig\Extension\Profiler_Extension;
use Twig\Profiler\Profile;
use Twig\Runtime\Escaper_Runtime;
use Twig\Twig_Function;
/**
 * Twig extension for the profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Web_Profiler_Extension extends Profiler_Extension
{
    /**
     * @var resource
     */
    private $output;
    private int $stack_level = 0;
    public function __construct(private readonly ?Html_Dumper $dumper = new Html_Dumper())
    {
        $this->dumper->set_output($this->output = fopen('php://memory', 'r+'));
    }
    public function enter(Profile $profile): void
    {
        ++$this->stack_level;
    }
    public function leave(Profile $profile): void
    {
        if (0 === --$this->stack_level) {
            $this->dumper->set_output($this->output = fopen('php://memory', 'r+'));
        }
    }
    public function get_functions(): array
    {
        return [new Twig_Function('profiler_dump', $this->dump_data(...), ['is_safe' => ['html'], 'needs_environment' => true]), new Twig_Function('profiler_dump_log', $this->dump_log(...), ['is_safe' => ['html'], 'needs_environment' => true])];
    }
    public function dump_data(Environment $env, Data $data, int $max_depth = 0): string
    {
        $this->dumper->set_charset($env->get_charset());
        $this->dumper->dump($data, null, ['maxDepth' => $max_depth]);
        $dump = stream_get_contents($this->output, -1, 0);
        rewind($this->output);
        ftruncate($this->output, 0);
        return str_replace("\n</pre", '</pre', rtrim($dump));
    }
    public function dump_log(Environment $env, string $message, ?Data $context = null): string
    {
        $message = self::escape($env, $message);
        $message = preg_replace('/&quot;(.*?)&quot;/', '&quot;<b>$1</b>&quot;', $message);
        $replacements = [];
        foreach ($context ?? [] as $k => $v) {
            $k = '{' . self::escape($env, $k) . '}';
            if (str_contains((string) $message, $k)) {
                $replacements[$k] = $v;
            }
        }
        if (!$replacements) {
            return '<span class="dump-inline">' . $message . '</span>';
        }
        foreach ($replacements as $k => $v) {
            $replacements['&quot;<b>' . $k . '</b>&quot;'] = $replacements['&quot;' . $k . '&quot;'] = $replacements[$k] = $this->dump_data($env, $v);
        }
        return '<span class="dump-inline">' . strtr($message, $replacements) . '</span>';
    }
    public function get_name(): string
    {
        return 'profiler';
    }
    private static function escape(Environment $env, string $s): string
    {
        return $env->get_runtime(Escaper_Runtime::class)->escape($s);
    }
}