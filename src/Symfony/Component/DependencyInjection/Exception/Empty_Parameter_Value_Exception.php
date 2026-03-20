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
namespace Symfony\Component\Dependency_Injection\Exception;

use Psr\Container\Not_Found_Exception_Interface;
/**
 * This exception is thrown when an existent parameter with an empty value is used.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Empty_Parameter_Value_Exception extends InvalidArgumentException implements Not_Found_Exception_Interface
{
}