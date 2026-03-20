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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\Token_Parser\Dump_Token_Parser;
use Symfony\Component\Var_Dumper\Cloner\Cloner_Interface;
use Symfony\Component\Var_Dumper\Dumper\Html_Dumper;
use Twig\Environment;
use Twig\Extension\Abstract_Extension;
use Twig\Template;
use Twig\Twig_Function;
/**
 * Provides integration of the dump() function with Twig.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Dump_Extension extends Abstract_Extension
{
    public function __construct(private readonly Cloner_Interface $cloner, private ?Html_Dumper $dumper = null)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('dump', $this->dump(...), ['is_safe' => ['html'], 'needs_context' => true, 'needs_environment' => true])];
    }
    public function get_token_parsers(): array
    {
        return [new Dump_Token_Parser()];
    }
    public function dump(Environment $env, array $context): ?string
    {
        if (!$env->is_debug()) {
            return null;
        }
        if (2 === \func_num_args()) {
            $vars = [];
            foreach ($context as $key => $value) {
                if (!$value instanceof Template) {
                    $vars[$key] = $value;
                }
            }
            $vars = [$vars];
        } else {
            $vars = \func_get_args();
            unset($vars[0], $vars[1]);
        }
        $dump = fopen('php://memory', 'r+');
        $this->dumper ??= new Html_Dumper();
        $this->dumper->set_charset($env->get_charset());
        foreach ($vars as $value) {
            $this->dumper->dump($this->cloner->clone_var($value), $dump);
        }
        return stream_get_contents($dump, -1, 0);
    }
}