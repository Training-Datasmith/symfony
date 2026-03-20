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
namespace Symfony\Component\Http_Client\Exception;

use Symfony\Contracts\Http_Client\Exception\Decoding_Exception_Interface;
/**
 * Thrown by responses' toArray() method when their content cannot be JSON-decoded.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Json_Exception extends \Json_Exception implements Decoding_Exception_Interface
{
}