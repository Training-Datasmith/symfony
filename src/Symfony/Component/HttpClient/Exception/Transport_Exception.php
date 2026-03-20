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

use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Transport_Exception extends \RuntimeException implements Transport_Exception_Interface
{
}