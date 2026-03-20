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
namespace Symfony\Component\Http_Foundation\Request_Matcher;

use Symfony\Component\Http_Foundation\Ip_Utils;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Matcher_Interface;
/**
 * Checks the client IP of a Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Ips_Request_Matcher implements Request_Matcher_Interface
{
    private readonly array $ips;
    /**
     * @param string[]|string $ips A specific IP address or a range specified using IP/netmask like 192.168.1.0/24
     *                             Strings can contain a comma-delimited list of IPs/ranges
     */
    public function __construct(array|string $ips)
    {
        $this->ips = array_reduce((array) $ips, static fn(array $ips, string $ip): array => array_merge($ips, preg_split('/\s*,\s*/', $ip)), []);
    }
    public function matches(Request $request): bool
    {
        if (!$this->ips) {
            return true;
        }
        return Ip_Utils::check_ip($request->get_client_ip() ?? '', $this->ips);
    }
}