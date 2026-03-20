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
class From_Callable_Configurator extends Abstract_Service_Configurator
{
    use Traits\Abstract_Trait;
    use Traits\Autoconfigure_Trait;
    use Traits\Autowire_Trait;
    use Traits\Bind_Trait;
    use Traits\Decorate_Trait;
    use Traits\Deprecate_Trait;
    use Traits\Lazy_Trait;
    use Traits\Public_Trait;
    use Traits\Share_Trait;
    use Traits\Tag_Trait;
    public const FACTORY = 'services';
    public function __construct(private Service_Configurator $service_configurator, Definition $definition)
    {
        parent::__construct($service_configurator->parent, $definition, $service_configurator->id);
    }
    public function __destruct()
    {
        $this->service_configurator->__destruct();
    }
}