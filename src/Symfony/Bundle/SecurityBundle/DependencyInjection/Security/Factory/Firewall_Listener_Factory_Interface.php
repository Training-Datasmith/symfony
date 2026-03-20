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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory;

use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Can be implemented by a security factory to add a listener to the firewall.
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
interface Firewall_Listener_Factory_Interface
{
    /**
     * Creates the firewall listener services for the provided configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return string[] The listener service IDs to be used by the firewall
     */
    public function create_listeners(Container_Builder $container, string $firewall_name, array $config): array;
}