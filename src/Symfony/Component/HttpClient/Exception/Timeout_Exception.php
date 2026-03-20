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

use Symfony\Contracts\Http_Client\Exception\Timeout_Exception_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Timeout_Exception extends Transport_Exception implements Timeout_Exception_Interface
{
}