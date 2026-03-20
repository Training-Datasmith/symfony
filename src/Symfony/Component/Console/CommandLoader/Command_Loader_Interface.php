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
namespace Symfony\Component\Console\Command_Loader;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface Command_Loader_Interface
{
    /**
     * Loads a command.
     *
     * @throws CommandNotFoundException
     */
    public function get(string $name): Command;
    /**
     * Checks if a command exists.
     */
    public function has(string $name): bool;
    /**
     * @return string[]
     */
    public function get_names(): array;
}