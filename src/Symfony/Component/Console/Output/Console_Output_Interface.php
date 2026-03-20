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
namespace Symfony\Component\Console\Output;

/**
 * ConsoleOutputInterface is the interface implemented by ConsoleOutput class.
 * This adds information about stderr and section output stream.
 *
 * @author Dariusz Górecki <darek.krk@gmail.com>
 */
interface Console_Output_Interface extends Output_Interface
{
    /**
     * Gets the OutputInterface for errors.
     */
    public function get_error_output(): Output_Interface;
    public function set_error_output(Output_Interface $error): void;
    public function section(): Console_Section_Output;
}