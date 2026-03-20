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
 * The HTTP request contains headers with conflicting information.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class Conflicting_Headers_Exception extends UnexpectedValueException implements Request_Exception_Interface
{
}