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
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Security\Http\Firewall_Map_Interface;
/**
 * This is a lazy-loading firewall map implementation.
 *
 * Listeners will only be initialized if we really need them.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Firewall_Map implements Firewall_Map_Interface
{
    public function __construct(private readonly Container_Interface $container, private readonly iterable $map)
    {
    }
    public function get_listeners(Request $request): array
    {
        $context = $this->get_firewall_context($request);
        if (null === $context) {
            return [[], null, null];
        }
        return [$context->get_listeners(), $context->get_exception_listener(), $context->get_logout_listener()];
    }
    public function get_firewall_config(Request $request): ?Firewall_Config
    {
        return $this->get_firewall_context($request)?->get_config();
    }
    private function get_firewall_context(Request $request): ?Firewall_Context
    {
        if ($request->attributes->has('_firewall_context')) {
            $stored_context_id = $request->attributes->get('_firewall_context');
            foreach ($this->map as $context_id => $request_matcher) {
                if ($context_id === $stored_context_id) {
                    return $this->container->get($context_id);
                }
            }
            $request->attributes->remove('_firewall_context');
        }
        foreach ($this->map as $context_id => $request_matcher) {
            if (null === $request_matcher || $request_matcher->matches($request)) {
                $request->attributes->set('_firewall_context', $context_id);
                return $this->container->get($context_id);
            }
        }
        return null;
    }
}