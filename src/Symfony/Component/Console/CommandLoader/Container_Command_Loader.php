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

use Psr\Container\Container_Interface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
/**
 * Loads commands from a PSR-11 container.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class Container_Command_Loader implements Command_Loader_Interface
{
    /**
     * @param array $commandMap An array with command names as keys and service ids as values
     */
    public function __construct(private readonly Container_Interface $container, private array $command_map)
    {
    }
    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw new Command_Not_Found_Exception(\sprintf('Command "%s" does not exist.', $name));
        }
        return $this->container->get($this->command_map[$name]);
    }
    public function has(string $name): bool
    {
        return isset($this->command_map[$name]) && $this->container->has($this->command_map[$name]);
    }
    public function get_names(): array
    {
        return array_keys($this->command_map);
    }
}