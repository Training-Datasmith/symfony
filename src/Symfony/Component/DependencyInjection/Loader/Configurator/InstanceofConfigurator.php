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

use Symfony\Component\Dependency_Injection\Definition;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Instanceof_Configurator extends Abstract_Service_Configurator
{
    use Traits\Autowire_Trait;
    use Traits\Bind_Trait;
    use Traits\Call_Trait;
    use Traits\Configurator_Trait;
    use Traits\Constructor_Trait;
    use Traits\Lazy_Trait;
    use Traits\Property_Trait;
    use Traits\Public_Trait;
    use Traits\Share_Trait;
    use Traits\Tag_Trait;
    public const FACTORY = 'instanceof';
    public function __construct(Services_Configurator $parent, Definition $definition, string $id, private ?string $path = null)
    {
        parent::__construct($parent, $definition, $id);
    }
    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     */
    final public function instanceof(string $fqcn): self
    {
        return $this->parent->instanceof($fqcn);
    }
}