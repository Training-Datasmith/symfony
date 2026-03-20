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
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
/**
 * Yields the Session.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class Session_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if (!$request->has_session()) {
            return [];
        }
        $type = $argument->get_type();
        if (Session_Interface::class !== $type && !is_subclass_of($type, Session_Interface::class)) {
            return [];
        }
        return $request->get_session() instanceof $type ? [$request->get_session()] : [];
    }
}