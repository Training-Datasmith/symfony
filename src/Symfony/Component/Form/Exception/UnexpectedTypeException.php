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
namespace Symfony\Component\Form\Exception;

class Unexpected_Type_Exception extends InvalidArgumentException
{
    public function __construct(mixed $value, string $expected_type)
    {
        parent::__construct(\sprintf('Expected argument of type "%s", "%s" given', $expected_type, get_debug_type($value)));
    }
}