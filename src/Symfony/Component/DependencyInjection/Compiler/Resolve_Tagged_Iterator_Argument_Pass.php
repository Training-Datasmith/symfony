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

use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
/**
 * Resolves all TaggedIteratorArgument arguments.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Resolve_Tagged_Iterator_Argument_Pass extends Abstract_Recursive_Pass
{
    use Priority_Tagged_Service_Trait;
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Tagged_Iterator_Argument) {
            return parent::process_value($value, $is_root);
        }
        $exclude = $value->get_exclude();
        if ($value->exclude_self()) {
            $exclude[] = $this->current_id;
        }
        $value->set_values($this->find_and_sort_tagged_services($value, $this->container, $exclude));
        return $value;
    }
}