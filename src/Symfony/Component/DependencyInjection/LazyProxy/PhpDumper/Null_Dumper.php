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
namespace Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper;

use Symfony\Component\Dependency_Injection\Definition;
/**
 * Null dumper, negates any proxy code generation for any given service definition.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @final
 */
class Null_Dumper implements Dumper_Interface
{
    public function is_proxy_candidate(Definition $definition, ?bool &$as_ghost_object = null, ?string $id = null): bool
    {
        return $as_ghost_object = false;
    }
    public function get_proxy_factory_code(Definition $definition, string $id, string $factory_code): string
    {
        return '';
    }
    public function get_proxy_code(Definition $definition, ?string $id = null): string
    {
        return '';
    }
}