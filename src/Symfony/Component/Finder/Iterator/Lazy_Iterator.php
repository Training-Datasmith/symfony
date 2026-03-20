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
namespace Symfony\Component\Finder\Iterator;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal
 */
class Lazy_Iterator implements \IteratorAggregate
{
    private readonly \Closure $iterator_factory;
    public function __construct(callable $iterator_factory)
    {
        $this->iterator_factory = $iterator_factory(...);
    }
    public function getIterator(): \Traversable
    {
        yield from ($this->iterator_factory)();
    }
}