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

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Service_Configurator extends Abstract_Service_Configurator
{
    use Traits\Abstract_Trait;
    use Traits\Argument_Trait;
    use Traits\Autoconfigure_Trait;
    use Traits\Autowire_Trait;
    use Traits\Bind_Trait;
    use Traits\Call_Trait;
    use Traits\Class_Trait;
    use Traits\Configurator_Trait;
    use Traits\Constructor_Trait;
    use Traits\Decorate_Trait;
    use Traits\Deprecate_Trait;
    use Traits\Factory_Trait;
    use Traits\File_Trait;
    use Traits\From_Callable_Trait;
    use Traits\Lazy_Trait;
    use Traits\Parent_Trait;
    use Traits\Property_Trait;
    use Traits\Public_Trait;
    use Traits\Share_Trait;
    use Traits\Synthetic_Trait;
    use Traits\Tag_Trait;
    public const FACTORY = 'services';
    private bool $destructed = false;
    public function __construct(private Container_Builder $container, private array $instanceof, private bool $allow_parent, Services_Configurator $parent, Definition $definition, ?string $id, array $default_tags, private ?string $path = null)
    {
        parent::__construct($parent, $definition, $id, $default_tags);
    }
    public function __destruct()
    {
        if ($this->destructed) {
            return;
        }
        $this->destructed = true;
        parent::__destruct();
        $this->container->remove_bindings($this->id);
        $this->container->set_definition($this->id, $this->definition->set_instanceof_conditionals($this->instanceof));
    }
}