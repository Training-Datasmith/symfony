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

use Symfony\Contracts\Http_Client\Exception\Redirection_Exception_Interface;
/**
 * Represents a 3xx response.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Redirection_Exception extends \RuntimeException implements Redirection_Exception_Interface
{
    use Http_Exception_Trait;
}