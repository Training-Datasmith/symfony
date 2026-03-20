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
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Checks the validity of references.
 *
 * The following checks are performed by this pass:
 * - target definitions are not abstract
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Check_Reference_Validity_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($is_root && $value instanceof Definition && ($value->is_synthetic() || $value->is_abstract())) {
            return $value;
        }
        if ($value instanceof Reference && $this->container->has_definition((string) $value)) {
            $target_definition = $this->container->get_definition((string) $value);
            if ($target_definition->is_abstract()) {
                throw new RuntimeException(\sprintf('The definition "%s" has a reference to an abstract definition "%s". Abstract definitions cannot be the target of references.', $this->current_id, $value));
            }
        }
        return parent::process_value($value, $is_root);
    }
}