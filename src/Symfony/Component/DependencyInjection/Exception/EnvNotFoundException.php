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

/**
 * This exception is thrown when an environment variable is not found.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Env_Not_Found_Exception extends InvalidArgumentException
{
}