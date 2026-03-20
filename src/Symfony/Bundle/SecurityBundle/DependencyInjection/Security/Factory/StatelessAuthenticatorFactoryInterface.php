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
 * Stateless authenticators are authenticators that can work without a user provider.
 *
 * This situation can only occur in stateless firewalls, as statefull firewalls
 * need the user provider to refresh the user in each subsequent request. A
 * stateless authenticator can be used on both stateless and statefull authenticators.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface Stateless_Authenticator_Factory_Interface extends Authenticator_Factory_Interface
{
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, ?string $user_provider_id): string|array;
}