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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Var_Dumper\Cloner\Cloner_Interface;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper;
/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Dumper
{
    private \Closure $handler;
    public function __construct(private readonly Output_Interface $output, private ?Cli_Dumper $dumper = null, private ?Cloner_Interface $cloner = null)
    {
        if (class_exists(Cli_Dumper::class)) {
            $this->handler = function ($var): string {
                $dumper = $this->dumper ??= new Cli_Dumper(null, null, Cli_Dumper::DUMP_LIGHT_ARRAY | Cli_Dumper::DUMP_COMMA_SEPARATOR);
                $dumper->set_colors($this->output->is_decorated());
                return rtrim((string) $dumper->dump(($this->cloner ??= new Var_Cloner())->clone_var($var)->with_ref_handles(false), true));
            };
        } else {
            $this->handler = static fn($var): string => match (true) {
                null === $var => 'null',
                true === $var => 'true',
                false === $var => 'false',
                \is_string($var) => '"' . $var . '"',
                default => rtrim(print_r($var, true)),
            };
        }
    }
    public function __invoke(mixed $var): string
    {
        return ($this->handler)($var);
    }
}