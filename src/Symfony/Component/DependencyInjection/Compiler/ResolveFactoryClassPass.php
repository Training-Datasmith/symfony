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

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class Resolve_Factory_Class_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Definition && \is_array($factory = $value->get_factory()) && null === $factory[0]) {
            if (null === $class = $value->get_class()) {
                throw new RuntimeException(\sprintf('The "%s" service is defined to be created by a factory, but is missing the factory class. Did you forget to define the factory or service class?', $this->current_id));
            }
            $factory[0] = $class;
            $value->set_factory($factory);
        }
        return parent::process_value($value, $is_root);
    }
}