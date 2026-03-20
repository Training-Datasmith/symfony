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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Dependency_Injection\Alias;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Alias_Configurator extends Abstract_Service_Configurator
{
    use Traits\Deprecate_Trait;
    use Traits\Public_Trait;
    public const FACTORY = 'alias';
    public function __construct(Services_Configurator $parent, Alias $alias)
    {
        $this->parent = $parent;
        $this->definition = $alias;
    }
}