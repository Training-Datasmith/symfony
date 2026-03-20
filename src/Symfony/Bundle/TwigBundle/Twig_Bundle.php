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
namespace Symfony\Bundle\Twig_Bundle;

use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler\Attribute_Extension_Pass;
use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler\Extension_Pass;
use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler\Runtime_Loader_Pass;
use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler\Twig_Environment_Pass;
use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler\Twig_Loader_Pass;
use Symfony\Component\Console\Application;
use Symfony\Component\Dependency_Injection\Compiler\Pass_Config;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Http_Kernel\Bundle\Bundle;
/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Bundle extends Bundle
{
    public function build(Container_Builder $container): void
    {
        parent::build($container);
        // ExtensionPass must be run before the FragmentRendererPass as it adds tags that are processed later
        $container->add_compiler_pass(new Extension_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, 10);
        $container->add_compiler_pass(new Attribute_Extension_Pass());
        $container->add_compiler_pass(new Twig_Environment_Pass());
        $container->add_compiler_pass(new Twig_Loader_Pass());
        $container->add_compiler_pass(new Runtime_Loader_Pass(), Pass_Config::TYPE_BEFORE_REMOVING);
    }
    public function register_commands(Application $application): void
    {
        // noop
    }
}