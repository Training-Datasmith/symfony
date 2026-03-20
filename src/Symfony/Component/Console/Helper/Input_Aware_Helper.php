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

use Symfony\Component\Console\Input\Input_Aware_Interface;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * An implementation of InputAwareInterface for Helpers.
 *
 * @author Wouter J <waldio.webdesign@gmail.com>
 */
abstract class Input_Aware_Helper extends Helper implements Input_Aware_Interface
{
    protected Input_Interface $input;
    public function set_input(Input_Interface $input): void
    {
        $this->input = $input;
    }
}