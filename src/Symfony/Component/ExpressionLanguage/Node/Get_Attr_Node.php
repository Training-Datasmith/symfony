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
namespace Symfony\Component\Expression_Language\Node;

use Symfony\Component\Expression_Language\Compiler;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Get_Attr_Node extends Node
{
    public const PROPERTY_CALL = 1;
    public const METHOD_CALL = 2;
    public const ARRAY_CALL = 3;
    /**
     * @param self::* $type
     */
    public function __construct(Node $node, Node $attribute, Array_Node $arguments, int $type, bool $is_null_safe = false)
    {
        $is_null_safe = self::ARRAY_CALL === $type && $is_null_safe;
        parent::__construct(['node' => $node, 'attribute' => $attribute, 'arguments' => $arguments], ['type' => $type, 'is_null_coalesce' => false, 'is_short_circuited' => false, 'is_null_safe' => $is_null_safe]);
    }
    public function compile(Compiler $compiler): void
    {
        $null_safe = $this->nodes['attribute'] instanceof Constant_Node && $this->nodes['attribute']->is_null_safe || $this->attributes['is_null_safe'];
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $compiler->compile($this->nodes['node'])->raw($null_safe ? '?->' : '->')->raw($this->nodes['attribute']->attributes['value']);
                break;
            case self::METHOD_CALL:
                $compiler->compile($this->nodes['node'])->raw($null_safe ? '?->' : '->')->raw($this->nodes['attribute']->attributes['value'])->raw('(')->compile($this->nodes['arguments'])->raw(')');
                break;
            case self::ARRAY_CALL:
                if ($null_safe) {
                    $compiler->raw('\\' . self::class . '::convertToArrayAccess(')->compile($this->nodes['node'])->raw(', ')->string($this->nodes['node']->dump())->raw(')?->offsetGet(')->compile($this->nodes['attribute'])->raw(')');
                } else {
                    $compiler->compile($this->nodes['node'])->raw('[')->compile($this->nodes['attribute'])->raw(']');
                }
                break;
        }
    }
    public function evaluate(array $functions, array $values): mixed
    {
        $null_safe = $this->attributes['is_null_safe'];
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values);
                if (null === $obj && ($this->nodes['attribute']->is_null_safe || $this->attributes['is_null_coalesce'])) {
                    $this->attributes['is_short_circuited'] = true;
                    return null;
                }
                if (null === $obj && $this->is_short_circuited()) {
                    return null;
                }
                if (!\is_object($obj)) {
                    throw new \RuntimeException(\sprintf('Unable to get property "%s" of non-object "%s".', $this->nodes['attribute']->dump(), $this->nodes['node']->dump()));
                }
                $property = $this->nodes['attribute']->attributes['value'];
                if ($this->attributes['is_null_coalesce']) {
                    return $obj->{$property} ?? null;
                }
                return $obj->{$property};
            case self::METHOD_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values);
                if (null === $obj && $this->nodes['attribute']->is_null_safe) {
                    $this->attributes['is_short_circuited'] = true;
                    return null;
                }
                if (null === $obj && $this->is_short_circuited()) {
                    return null;
                }
                if (!\is_object($obj)) {
                    throw new \RuntimeException(\sprintf('Unable to call method "%s" of non-object "%s".', $this->nodes['attribute']->dump(), $this->nodes['node']->dump()));
                }
                if (!\is_callable($to_call = [$obj, $this->nodes['attribute']->attributes['value']])) {
                    throw new \RuntimeException(\sprintf('Unable to call method "%s" of object "%s".', $this->nodes['attribute']->attributes['value'], get_debug_type($obj)));
                }
                return $to_call(...array_values($this->nodes['arguments']->evaluate($functions, $values)));
            case self::ARRAY_CALL:
                $array = $this->nodes['node']->evaluate($functions, $values);
                if (null === $array && ($null_safe || $this->is_short_circuited())) {
                    $this->attributes['is_short_circuited'] = $null_safe || $this->attributes['is_short_circuited'];
                    return null;
                }
                if (!\is_array($array) && !$array instanceof \ArrayAccess && !(null === $array && $this->attributes['is_null_coalesce'])) {
                    throw new \RuntimeException(\sprintf('Unable to get an item of non-array "%s".', $this->nodes['node']->dump()));
                }
                if ($this->attributes['is_null_coalesce']) {
                    return $array[$this->nodes['attribute']->evaluate($functions, $values)] ?? null;
                }
                return $array[$this->nodes['attribute']->evaluate($functions, $values)];
        }
    }
    /**
     * @internal
     */
    public static function convert_to_array_access(mixed $value, string $node_dump): ?\ArrayAccess
    {
        if (null === $value) {
            return null;
        }
        if (\is_array($value)) {
            return new \ArrayObject($value);
        }
        if ($value instanceof \ArrayAccess) {
            return $value;
        }
        throw new \RuntimeException(\sprintf('Unable to get an item of non-array "%s".', $node_dump));
    }
    private function is_short_circuited(): bool
    {
        return $this->attributes['is_short_circuited'] || $this->nodes['node'] instanceof self && $this->nodes['node']->is_short_circuited();
    }
    public function to_array(): array
    {
        $null_safe = $this->nodes['attribute'] instanceof Constant_Node && $this->nodes['attribute']->is_null_safe;
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                return [$this->nodes['node'], $null_safe ? '?.' : '.', $this->nodes['attribute']];
            case self::METHOD_CALL:
                return [$this->nodes['node'], $null_safe ? '?.' : '.', $this->nodes['attribute'], '(', $this->nodes['arguments'], ')'];
            case self::ARRAY_CALL:
                return [$this->nodes['node'], $this->attributes['is_null_safe'] ? '?.[' : '[', $this->nodes['attribute'], ']'];
        }
    }
    /**
     * Provides BC with instances serialized before v6.2.
     */
    public function __unserialize(array $data): void
    {
        $this->nodes = $data['nodes'];
        $this->attributes = $data['attributes'];
        $this->attributes['is_null_coalesce'] ??= false;
        $this->attributes['is_null_safe'] ??= false;
        $this->attributes['is_short_circuited'] ??= $data["\x00Symfony\\Component\\ExpressionLanguage\\Node\\GetAttrNode\x00isShortCircuited"] ?? false;
    }
}