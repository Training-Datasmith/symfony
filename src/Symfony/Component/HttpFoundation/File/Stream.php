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
namespace Symfony\Component\Http_Foundation\File;

/**
 * A PHP stream of unknown size.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Stream extends File
{
    public function get_size(): int|false
    {
        return false;
    }
}