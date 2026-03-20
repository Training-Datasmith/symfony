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
 * Raised when a user has performed an operation that should be considered
 * suspicious from a security perspective.
 */
class Suspicious_Operation_Exception extends UnexpectedValueException implements Request_Exception_Interface
{
}