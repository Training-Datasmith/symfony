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
namespace Symfony\Component\Form\Extension\Validator\Violation_Mapper;

use Symfony\Component\Form\Exception\Error_Mapping_Exception;
use Symfony\Component\Form\Form_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Mapping_Rule
{
    public function __construct(private readonly Form_Interface $origin, private string $property_path, private readonly string $target_path)
    {
    }
    public function get_origin(): Form_Interface
    {
        return $this->origin;
    }
    /**
     * Matches a property path against the rule path.
     *
     * If the rule matches, the form mapped by the rule is returned.
     * Otherwise this method returns false.
     */
    public function match(string $property_path): ?Form_Interface
    {
        return $property_path === $this->property_path ? $this->get_target() : null;
    }
    /**
     * Matches a property path against a prefix of the rule path.
     */
    public function is_prefix(string $property_path): bool
    {
        $length = \strlen($property_path);
        $prefix = substr($this->property_path, 0, $length);
        $next = $this->property_path[$length] ?? null;
        return $prefix === $property_path && ('[' === $next || '.' === $next);
    }
    /**
     * @throws ErrorMappingException
     */
    public function get_target(): Form_Interface
    {
        $child_names = explode('.', $this->target_path);
        $target = $this->origin;
        foreach ($child_names as $child_name) {
            if (!$target->has($child_name)) {
                throw new Error_Mapping_Exception(\sprintf('The child "%s" of "%s" mapped by the rule "%s" in "%s" does not exist.', $child_name, $target->get_name(), $this->target_path, $this->origin->get_name()));
            }
            $target = $target->get($child_name);
        }
        return $target;
    }
}