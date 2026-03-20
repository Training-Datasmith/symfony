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
namespace Symfony\Component\Form;

class Callback_Transformer implements Data_Transformer_Interface
{
    private readonly \Closure $transform;
    private readonly \Closure $reverse_transform;
    public function __construct(callable $transform, callable $reverse_transform)
    {
        $this->transform = $transform(...);
        $this->reverse_transform = $reverse_transform(...);
    }
    public function transform(mixed $data): mixed
    {
        return ($this->transform)($data);
    }
    public function reverse_transform(mixed $data): mixed
    {
        return ($this->reverse_transform)($data);
    }
}