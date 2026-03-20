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
namespace Symfony\Component\Http_Foundation;

/**
 * Http utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Ip_Utils
{
    public const PRIVATE_SUBNETS = [
        '127.0.0.0/8',
        // RFC1700 (Loopback)
        '10.0.0.0/8',
        // RFC1918
        '192.168.0.0/16',
        // RFC1918
        '172.16.0.0/12',
        // RFC1918
        '169.254.0.0/16',
        // RFC3927
        '0.0.0.0/8',
        // RFC5735
        '240.0.0.0/4',
        // RFC1112
        '::1/128',
        // Loopback
        'fc00::/7',
        // Unique Local Address
        'fe80::/10',
        // Link Local Address
        '::ffff:0:0/96',
        // IPv4 translations
        '::/128',
    ];
    private static array $checked_ips = [];
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }
    /**
     * Checks if an IPv4 or IPv6 address is contained in the list of given IPs or subnets.
     *
     * @param string|array $ips List of IPs or subnets (can be a string if only a single one)
     */
    public static function check_ip(string $request_ip, string|array $ips): bool
    {
        if (!\is_array($ips)) {
            $ips = [$ips];
        }
        $method = substr_count($request_ip, ':') > 1 ? 'checkIp6' : 'checkIp4';
        foreach ($ips as $ip) {
            if (self::$method($request_ip, $ip)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Compares two IPv4 addresses.
     * In case a subnet is given, it checks if it contains the request IP.
     *
     * @param string $ip IPv4 address or subnet in CIDR notation
     *
     * @return bool Whether the request IP matches the IP, or whether the request IP is within the CIDR subnet
     */
    public static function check_ip4(string $request_ip, string $ip): bool
    {
        $cache_key = $request_ip . '-' . $ip . '-v4';
        if (null !== $cache_value = self::get_cache_result($cache_key)) {
            return $cache_value;
        }
        if (!filter_var($request_ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return self::set_cache_result($cache_key, false);
        }
        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);
            if ('0' === $netmask) {
                return self::set_cache_result($cache_key, false !== filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4));
            }
            if ($netmask < 0 || $netmask > 32) {
                return self::set_cache_result($cache_key, false);
            }
        } else {
            $address = $ip;
            $netmask = 32;
        }
        if (false === ip2long($address)) {
            return self::set_cache_result($cache_key, false);
        }
        return self::set_cache_result($cache_key, 0 === substr_compare(\sprintf('%032b', ip2long($request_ip)), \sprintf('%032b', ip2long($address)), 0, $netmask));
    }
    /**
     * Compares two IPv6 addresses.
     * In case a subnet is given, it checks if it contains the request IP.
     *
     * @author David Soria Parra <dsp at php dot net>
     *
     * @see https://github.com/dsp/v6tools
     *
     * @param string $ip IPv6 address or subnet in CIDR notation
     *
     * @throws \RuntimeException When IPV6 support is not enabled
     */
    public static function check_ip6(string $request_ip, string $ip): bool
    {
        $cache_key = $request_ip . '-' . $ip . '-v6';
        if (null !== $cache_value = self::get_cache_result($cache_key)) {
            return $cache_value;
        }
        if (!(\extension_loaded('sockets') && \defined('AF_INET6') || @inet_pton('::1'))) {
            throw new \RuntimeException('Unable to check Ipv6. Check that PHP was not compiled with option "disable-ipv6".');
        }
        // Check to see if we were given a IP4 $requestIp or $ip by mistake
        if (!filter_var($request_ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return self::set_cache_result($cache_key, false);
        }
        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);
            if (!filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                return self::set_cache_result($cache_key, false);
            }
            if ('0' === $netmask) {
                return (bool) unpack('n*', @inet_pton($address));
            }
            if ($netmask < 1 || $netmask > 128) {
                return self::set_cache_result($cache_key, false);
            }
        } else {
            if (!filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                return self::set_cache_result($cache_key, false);
            }
            $address = $ip;
            $netmask = 128;
        }
        $bytes_addr = unpack('n*', @inet_pton($address));
        $bytes_test = unpack('n*', @inet_pton($request_ip));
        if (!$bytes_addr || !$bytes_test) {
            return self::set_cache_result($cache_key, false);
        }
        for ($i = 1, $ceil = ceil($netmask / 16); $i <= $ceil; ++$i) {
            $left = $netmask - 16 * ($i - 1);
            $left = $left <= 16 ? $left : 16;
            $mask = ~(0xffff >> $left) & 0xffff;
            if (($bytes_addr[$i] & $mask) != ($bytes_test[$i] & $mask)) {
                return self::set_cache_result($cache_key, false);
            }
        }
        return self::set_cache_result($cache_key, true);
    }
    /**
     * Anonymizes an IP/IPv6.
     *
     * Removes the last bytes of IPv4 and IPv6 addresses (1 byte for IPv4 and 8 bytes for IPv6 by default).
     *
     * @param int<0, 4>  $v4Bytes
     * @param int<0, 16> $v6Bytes
     */
    public static function anonymize(string $ip, int $v4Bytes = 1, int $v6Bytes = 8): string
    {
        if ($v6Bytes < 0) {
            throw new \InvalidArgumentException('Cannot anonymize less than 0 bytes.');
        }
        if ($v6Bytes > 16) {
            throw new \InvalidArgumentException('Cannot anonymize more than 4 bytes for IPv4 and 16 bytes for IPv6.');
        }
        /*
         * If the IP contains a % symbol, then it is a local-link address with scoping according to RFC 4007
         * In that case, we only care about the part before the % symbol, as the following functions, can only work with
         * the IP address itself. As the scope can leak information (containing interface name), we do not want to
         * include it in our anonymized IP data.
         */
        if (str_contains($ip, '%')) {
            $ip = substr($ip, 0, strpos($ip, '%'));
        }
        $wrapped_i_pv6 = false;
        if (str_starts_with($ip, '[') && str_ends_with($ip, ']')) {
            $wrapped_i_pv6 = true;
            $ip = substr($ip, 1, -1);
        }
        $mapped_ip_v4mask_generator = static function (string $mask, int $bytes_to_anonymize): string {
            $mask .= str_repeat('ff', 4 - $bytes_to_anonymize);
            $mask .= str_repeat('00', $bytes_to_anonymize);
            return '::' . implode(':', str_split($mask, 4));
        };
        $packed_address = inet_pton($ip);
        if (4 === \strlen($packed_address)) {
            $mask = rtrim(str_repeat('255.', 4 - $v4Bytes) . str_repeat('0.', $v4Bytes), '.');
        } elseif ($ip === inet_ntop($packed_address & inet_pton('::ffff:ffff:ffff'))) {
            $mask = $mapped_ip_v4mask_generator('ffff', $v4Bytes);
        } elseif ($ip === inet_ntop($packed_address & inet_pton('::ffff:ffff'))) {
            $mask = $mapped_ip_v4mask_generator('', $v4Bytes);
        } else {
            $mask = str_repeat('ff', 16 - $v6Bytes) . str_repeat('00', $v6Bytes);
            $mask = implode(':', str_split($mask, 4));
        }
        $ip = inet_ntop($packed_address & inet_pton($mask));
        if ($wrapped_i_pv6) {
            return '[' . $ip . ']';
        }
        return $ip;
    }
    /**
     * Checks if an IPv4 or IPv6 address is contained in the list of private IP subnets.
     */
    public static function is_private_ip(string $request_ip): bool
    {
        return self::check_ip($request_ip, self::PRIVATE_SUBNETS);
    }
    private static function get_cache_result(string $cache_key): ?bool
    {
        if (isset(self::$checked_ips[$cache_key])) {
            // Move the item last in cache (LRU)
            $value = self::$checked_ips[$cache_key];
            unset(self::$checked_ips[$cache_key]);
            self::$checked_ips[$cache_key] = $value;
            return self::$checked_ips[$cache_key];
        }
        return null;
    }
    private static function set_cache_result(string $cache_key, bool $result): bool
    {
        if (1000 < \count(self::$checked_ips)) {
            // stop memory leak if there are many keys
            self::$checked_ips = \array_slice(self::$checked_ips, 500, null, true);
        }
        return self::$checked_ips[$cache_key] = $result;
    }
}