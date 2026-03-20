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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Container_Builder;
class Remove_Build_Parameters_Pass implements Compiler_Pass_Interface
{
    /**
     * @var array<string, mixed>
     */
    private array $removed_parameters = [];
    public function __construct(private readonly bool $preserve_arrays = false)
    {
    }
    public function process(Container_Builder $container): void
    {
        $parameter_bag = $container->get_parameter_bag();
        $this->removed_parameters = [];
        foreach ($parameter_bag->all() as $name => $value) {
            if ('.' === ($name[0] ?? '') && (!$this->preserve_arrays || !\is_array($value))) {
                $this->removed_parameters[$name] = $value;
                $parameter_bag->remove($name);
                $container->log($this, \sprintf('Removing build parameter "%s".', $name));
            }
        }
    }
    /**
     * @return array<string, mixed>
     */
    public function get_removed_parameters(): array
    {
        return $this->removed_parameters;
    }
}