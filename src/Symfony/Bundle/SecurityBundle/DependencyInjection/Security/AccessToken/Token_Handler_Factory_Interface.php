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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Access_Token;

use Symfony\Component\Config\Definition\Builder\Node_Builder;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Allows creating configurable token handlers.
 */
interface Token_Handler_Factory_Interface
{
    /**
     * Creates a generic token handler service.
     */
    public function create(Container_Builder $container, string $id, array|string $config): void;
    /**
     * Gets a generic token handler configuration key.
     */
    public function get_key(): string;
    /**
     * Adds a generic token handler configuration.
     */
    public function add_configuration(Node_Builder $node): void;
}