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
 * Interface for Request exceptions.
 *
 * Exceptions implementing this interface should trigger an HTTP 400 response in the application code.
 */
interface Request_Exception_Interface
{
}