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

use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface Authenticator_Factory_Interface
{
    /**
     * Defines the priority at which the authenticator is called.
     */
    public function get_priority(): int;
    /**
     * Defines the configuration key used to reference the provider
     * in the firewall configuration.
     */
    public function get_key(): string;
    public function add_configuration(Node_Definition $builder): void;
    /**
     * Creates the authenticator service(s) for the provided configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return string|string[] The authenticator service ID(s) to be used by the firewall
     */
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string|array;
}