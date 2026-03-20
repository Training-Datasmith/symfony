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
namespace Symfony\Component\Console\Argument_Resolver\Value_Resolver;

use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Responsible for resolving the value of a Command argument based on its
 * parameter metadata and the Command MapInput.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface Value_Resolver_Interface
{
    /**
     * Returns the possible value(s) for the argument.
     */
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable;
}