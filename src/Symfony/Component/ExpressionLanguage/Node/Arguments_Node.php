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
class Arguments_Node extends Array_Node
{
    public function compile(Compiler $compiler): void
    {
        $this->compile_arguments($compiler, false);
    }
    public function to_array(): array
    {
        $array = [];
        foreach ($this->get_key_value_pairs() as $pair) {
            $array[] = $pair['value'];
            $array[] = ', ';
        }
        array_pop($array);
        return $array;
    }
}