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
namespace Symfony\Component\Console\Helper;

/**
 * @internal
 */
class Table_Rows implements \IteratorAggregate
{
    public function __construct(private readonly \Closure $generator)
    {
    }
    public function getIterator(): \Traversable
    {
        return ($this->generator)();
    }
}