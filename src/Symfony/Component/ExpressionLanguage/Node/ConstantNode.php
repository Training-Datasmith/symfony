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
class Constant_Node extends Node
{
    public function __construct(mixed $value, private readonly bool $is_identifier = false, public readonly bool $is_null_safe = false)
    {
        parent::__construct([], ['value' => $value]);
    }
    public function compile(Compiler $compiler): void
    {
        $compiler->repr($this->attributes['value']);
    }
    public function evaluate(array $functions, array $values): mixed
    {
        return $this->attributes['value'];
    }
    public function to_array(): array
    {
        $array = [];
        $value = $this->attributes['value'];
        if ($this->is_identifier) {
            $array[] = $value;
        } elseif (true === $value) {
            $array[] = 'true';
        } elseif (false === $value) {
            $array[] = 'false';
        } elseif (null === $value) {
            $array[] = 'null';
        } elseif (is_numeric($value)) {
            $array[] = $value;
        } elseif (!\is_array($value)) {
            $array[] = $this->dump_string($value);
        } elseif ($this->is_hash($value)) {
            foreach ($value as $k => $v) {
                $array[] = ', ';
                $array[] = new self($k);
                $array[] = ': ';
                $array[] = new self($v);
            }
            $array[0] = '{';
            $array[] = '}';
        } else {
            foreach ($value as $v) {
                $array[] = ', ';
                $array[] = new self($v);
            }
            $array[0] = '[';
            $array[] = ']';
        }
        return $array;
    }
}