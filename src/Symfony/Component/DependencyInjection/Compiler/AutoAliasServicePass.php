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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
/**
 * Sets a service to be an alias of another one, given a format pattern.
 */
class Auto_Alias_Service_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->find_tagged_service_ids('auto_alias') as $service_id => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['format'])) {
                    throw new InvalidArgumentException(\sprintf('Missing tag information "format" on auto_alias service "%s".', $service_id));
                }
                $alias_id = $container->get_parameter_bag()->resolve_value($tag['format']);
                if ($container->has_definition($alias_id) || $container->has_alias($alias_id)) {
                    $alias = new Alias($alias_id, $container->get_definition($service_id)->is_public());
                    $container->set_alias($service_id, $alias);
                }
            }
        }
    }
}