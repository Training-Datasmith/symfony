<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Exception;

/**
 * ConnectionException is thrown if binding to ldap cannot be established.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ConnectionException extends \RuntimeException implements ExceptionInterface
{
}
