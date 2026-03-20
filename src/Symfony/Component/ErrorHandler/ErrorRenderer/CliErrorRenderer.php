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
namespace Symfony\Component\Error_Handler\Error_Renderer;

use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper;
// Help opcache.preload discover always-needed symbols
class_exists(Cli_Dumper::class);
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Cli_Error_Renderer implements Error_Renderer_Interface
{
    public function render(\Throwable $exception): Flatten_Exception
    {
        $cloner = new Var_Cloner();
        $dumper = new class extends Cli_Dumper
        {
            protected function supports_colors(): bool
            {
                $output_stream = $this->output_stream;
                $this->output_stream = fopen('php://stdout', 'w');
                try {
                    return parent::supports_colors();
                } finally {
                    $this->output_stream = $output_stream;
                }
            }
        };
        return Flatten_Exception::create_from_throwable($exception)->set_as_string($dumper->dump($cloner->clone_var($exception), true));
    }
}