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
class Inline_Service_Configurator extends Abstract_Configurator
{
    use Traits\Argument_Trait;
    use Traits\Autowire_Trait;
    use Traits\Bind_Trait;
    use Traits\Call_Trait;
    use Traits\Configurator_Trait;
    use Traits\Constructor_Trait;
    use Traits\Factory_Trait;
    use Traits\File_Trait;
    use Traits\Lazy_Trait;
    use Traits\Parent_Trait;
    use Traits\Property_Trait;
    use Traits\Tag_Trait;
    public const FACTORY = 'service';
    private string $id = '[inline]';
    private bool $allow_parent = true;
    private ?string $path = null;
    public function __construct(Definition $definition)
    {
        $this->definition = $definition;
    }
}