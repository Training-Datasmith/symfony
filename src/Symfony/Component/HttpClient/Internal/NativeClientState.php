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
 * Internal representation of the native client's state.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Native_Client_State extends Client_State
{
    public int $id;
    public int $max_host_connections = \PHP_INT_MAX;
    public int $response_count = 0;
    /** @var string[] */
    public array $dns_cache = [];
    public bool $sleep = false;
    /** @var int[] */
    public array $hosts = [];
    public function __construct()
    {
        $this->id = random_int(\PHP_INT_MIN, \PHP_INT_MAX);
    }
    public function reset(): void
    {
        $this->response_count = 0;
        $this->dns_cache = [];
        $this->hosts = [];
    }
}