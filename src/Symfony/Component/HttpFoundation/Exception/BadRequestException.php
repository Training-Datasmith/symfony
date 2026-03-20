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
namespace Symfony\Component\Http_Foundation\Exception;

/**
 * Raised when a user sends a malformed request.
 */
class Bad_Request_Exception extends UnexpectedValueException implements Request_Exception_Interface
{
}