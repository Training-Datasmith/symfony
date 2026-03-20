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
namespace Symfony\Component\Http_Kernel\Controller\Argument_Resolver;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Uid\Abstract_Uid;
final class Uid_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if ($argument->is_variadic() || !\is_string($value = $request->attributes->get($argument->get_name())) || null === ($uid_class = $argument->get_type()) || !is_subclass_of($uid_class, Abstract_Uid::class, true)) {
            return [];
        }
        try {
            return [$uid_class::from_string($value)];
        } catch (\InvalidArgumentException $e) {
            throw new Not_Found_Http_Exception(\sprintf('The uid for the "%s" parameter is invalid.', $argument->get_name()), $e);
        }
    }
}