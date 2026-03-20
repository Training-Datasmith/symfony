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
namespace Symfony\Component\Http_Kernel\Controller;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
/**
 * Responsible for resolving the value of an argument based on its metadata.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Value_Resolver_Interface
{
    /**
     * Returns the possible value(s).
     */
    public function resolve(Request $request, Argument_Metadata $argument): iterable;
}