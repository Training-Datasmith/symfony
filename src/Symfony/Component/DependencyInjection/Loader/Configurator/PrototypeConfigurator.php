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
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Prototype_Configurator extends Abstract_Service_Configurator
{
    use Traits\Abstract_Trait;
    use Traits\Argument_Trait;
    use Traits\Autoconfigure_Trait;
    use Traits\Autowire_Trait;
    use Traits\Bind_Trait;
    use Traits\Call_Trait;
    use Traits\Configurator_Trait;
    use Traits\Constructor_Trait;
    use Traits\Deprecate_Trait;
    use Traits\Factory_Trait;
    use Traits\Lazy_Trait;
    use Traits\Parent_Trait;
    use Traits\Property_Trait;
    use Traits\Public_Trait;
    use Traits\Share_Trait;
    use Traits\Tag_Trait;
    public const FACTORY = 'load';
    private ?array $excludes = null;
    public function __construct(Services_Configurator $parent, private Php_File_Loader $loader, Definition $defaults, string $namespace, private string $resource, private bool $allow_parent, private ?string $path = null)
    {
        $definition = new Definition();
        $definition->set_public($defaults->is_public());
        $definition->set_autowired($defaults->is_autowired());
        $definition->set_autoconfigured($defaults->is_autoconfigured());
        // deep clone, to avoid multiple process of the same instance in the passes
        $definition->set_bindings(unserialize(serialize($defaults->get_bindings())));
        $definition->set_changes([]);
        parent::__construct($parent, $definition, $namespace, $defaults->get_tags());
    }
    public function __destruct()
    {
        parent::__destruct();
        if (isset($this->loader)) {
            $this->loader->register_classes($this->definition, $this->id, $this->resource, $this->excludes, $this->path);
        }
        unset($this->loader);
    }
    /**
     * Excludes files from registration using glob patterns.
     *
     * @param string[]|string $excludes
     *
     * @return $this
     */
    final public function exclude(array|string $excludes): static
    {
        $this->excludes = (array) $excludes;
        return $this;
    }
}