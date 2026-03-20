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
namespace Symfony\Component\Config\Definition\Exception;

/**
 * This exception is thrown if an invalid type is encountered.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Invalid_Type_Exception extends Invalid_Configuration_Exception
{
}