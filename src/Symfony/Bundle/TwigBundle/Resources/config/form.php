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

use Symfony\Bridge\Twig\Extension\Form_Extension;
use Symfony\Bridge\Twig\Form\Twig_Renderer_Engine;
use Symfony\Component\Form\Form_Renderer;
return static function (Container_Configurator $container): void {
    $container->services()->set('twig.extension.form', Form_Extension::class)->args([service('translator')->null_on_invalid()])->set('twig.form.engine', Twig_Renderer_Engine::class)->args([param('twig.form.resources'), service('twig')])->tag('kernel.reset', ['method' => '?reset'])->set('twig.form.renderer', Form_Renderer::class)->args([service('twig.form.engine'), service('security.csrf.token_manager')->null_on_invalid()])->tag('twig.runtime');
};