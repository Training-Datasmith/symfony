<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

class CallbackTransformer implements DataTransformerInterface
{
    private readonly \Closure $transform;
    private readonly \Closure $reverseTransform;

    public function __construct(callable $transform, callable $reverseTransform)
    {
        $this->transform = $transform(...);
        $this->reverseTransform = $reverseTransform(...);
    }

    public function transform(mixed $data): mixed
    {
        return ($this->transform)($data);
    }

    public function reverseTransform(mixed $data): mixed
    {
        return ($this->reverseTransform)($data);
    }
}
