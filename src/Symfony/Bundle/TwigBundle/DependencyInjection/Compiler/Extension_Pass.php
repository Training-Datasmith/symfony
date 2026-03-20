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
namespace Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler;

use Symfony\Bridge\Twig\Extension\Form_Extension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Emoji\Emoji_Transliterator;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Yaml\Yaml;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Extension_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!class_exists(Packages::class)) {
            $container->remove_definition('twig.extension.assets');
        }
        if (!class_exists(\Transliterator::class) || !class_exists(Emoji_Transliterator::class)) {
            $container->remove_definition('twig.extension.emoji');
        }
        if (!class_exists(Expression::class)) {
            $container->remove_definition('twig.extension.expression');
        }
        if (!interface_exists(Url_Generator_Interface::class)) {
            $container->remove_definition('twig.extension.routing');
        }
        if (!class_exists(Yaml::class)) {
            $container->remove_definition('twig.extension.yaml');
        }
        if (!$container->has('asset_mapper')) {
            // edge case where AssetMapper is installed, but not enabled
            $container->remove_definition('twig.extension.importmap');
            $container->remove_definition('twig.runtime.importmap');
        }
        $view_dir = \dirname((new \ReflectionClass(Form_Extension::class))->get_file_name(), 2) . '/Resources/views';
        $template_iterator = $container->get_definition('twig.template_iterator');
        $template_paths = $template_iterator->get_argument(1);
        $loader = $container->get_definition('twig.loader.native_filesystem');
        if ($container->has('mailer')) {
            $email_path = $view_dir . '/Email';
            $loader->add_method_call('addPath', [$email_path, 'email']);
            $loader->add_method_call('addPath', [$email_path, '!email']);
            $template_paths[$email_path] = 'email';
        }
        if ($container->has('form.extension')) {
            $container->get_definition('twig.extension.form')->add_tag('twig.extension');
            $core_theme_path = $view_dir . '/Form';
            $loader->add_method_call('addPath', [$core_theme_path]);
            $template_paths[$core_theme_path] = null;
        }
        $template_iterator->replace_argument(1, $template_paths);
        if ($container->has('router')) {
            $container->get_definition('twig.extension.routing')->add_tag('twig.extension');
        }
        if ($container->has('html_sanitizer')) {
            $container->get_definition('twig.extension.htmlsanitizer')->add_tag('twig.extension');
        }
        if ($container->has('fragment.handler')) {
            $container->get_definition('twig.extension.httpkernel')->add_tag('twig.extension');
            $container->get_definition('twig.runtime.httpkernel')->add_tag('twig.runtime');
            if ($container->has_definition('fragment.renderer.hinclude')) {
                $container->get_definition('fragment.renderer.hinclude')->add_tag('kernel.fragment_renderer', ['alias' => 'hinclude']);
            }
        }
        if ($container->has('request_stack')) {
            $container->get_definition('twig.extension.httpfoundation')->add_tag('twig.extension');
        }
        if ($container->get_parameter('kernel.debug')) {
            $container->get_definition('twig.extension.profiler')->add_tag('twig.extension');
            // only register if the improved version from DebugBundle is *not* present
            if (!$container->has('twig.extension.dump')) {
                $container->get_definition('twig.extension.debug')->add_tag('twig.extension');
            }
        }
        if ($container->has('web_link.add_link_header_listener')) {
            $container->get_definition('twig.extension.weblink')->add_tag('twig.extension');
        }
        $container->set_alias('twig.loader.filesystem', new Alias('twig.loader.native_filesystem', false));
        if ($container->has('assets.packages')) {
            $container->get_definition('twig.extension.assets')->add_tag('twig.extension');
        }
        if ($container->has_definition('twig.extension.yaml')) {
            $container->get_definition('twig.extension.yaml')->add_tag('twig.extension');
        }
        if (class_exists(Stopwatch::class)) {
            $container->get_definition('twig.extension.debug.stopwatch')->add_tag('twig.extension');
        }
        if ($container->has_definition('twig.extension.expression')) {
            $container->get_definition('twig.extension.expression')->add_tag('twig.extension');
        }
        if ($container->has_definition('twig.extension.emoji')) {
            $container->get_definition('twig.extension.emoji')->add_tag('twig.extension');
        }
        if (!class_exists(Workflow::class) || !$container->has('workflow.registry')) {
            $container->remove_definition('workflow.twig_extension');
        } else {
            $container->get_definition('workflow.twig_extension')->add_tag('twig.extension');
        }
        if ($container->has('serializer')) {
            $container->get_definition('twig.runtime.serializer')->add_tag('twig.runtime');
            $container->get_definition('twig.extension.serializer')->add_tag('twig.extension');
        }
    }
}