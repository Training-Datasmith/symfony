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
namespace Symfony\Bundle\Security_Bundle\Security;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Provides basic functionality for services mapped by the firewall name
 * in a container locator.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
trait Firewall_Aware_Trait
{
    private Container_Interface $locator;
    private Request_Stack $request_stack;
    private Firewall_Map $firewall_map;
    private function get_for_firewall(): object
    {
        $service_identifier = str_replace('FirewallAware', '', static::class);
        if (null === $request = $this->request_stack->get_current_request()) {
            throw new \LogicException('Cannot determine the correct ' . $service_identifier . ' to use: there is no active Request and so, the firewall cannot be determined. Try using a specific ' . $service_identifier . ' service.');
        }
        $firewall = $this->firewall_map->get_firewall_config($request);
        if (!$firewall) {
            throw new \LogicException('No ' . $service_identifier . ' found as the current route is not covered by a firewall.');
        }
        $firewall_name = $firewall->get_name();
        if (!$this->locator->has($firewall_name)) {
            $message = 'No ' . $service_identifier . ' found for this firewall.';
            if (\defined(static::class . '::FIREWALL_OPTION')) {
                $message .= \sprintf(' Did you forget to add a "' . static::FIREWALL_OPTION . '" key under your "%s" firewall?', $firewall_name);
            }
            throw new \LogicException($message);
        }
        return $this->locator->get($firewall_name);
    }
}