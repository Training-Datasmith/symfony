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
namespace Symfony\Component\Http_Client\Internal;

/**
 * Internal representation of the client state.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
class Client_State
{
    public array $handles_activity = [];
    public array $open_handles = [];
    public ?float $last_timeout = null;
}