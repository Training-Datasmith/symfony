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

use Symfony\Component\Http_Kernel\Attribute\With_Http_Status;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[With_Http_Status(404)]
abstract class Signed_Uri_Exception extends \RuntimeException implements Exception_Interface
{
}