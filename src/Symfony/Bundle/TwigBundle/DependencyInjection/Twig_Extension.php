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
namespace Symfony\Bundle\Twig_Bundle\Dependency_Injection;

use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler\Attribute_Extension_Pass;
use Symfony\Component\Asset_Mapper\Asset_Mapper;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Config\Resource\File_Existence_Resource;
use Symfony\Component\Console\Application;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Form\Form;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Translation\Locale_Switcher;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraint;
use Twig\Attribute\As_Twig_Filter;
use Twig\Attribute\As_Twig_Function;
use Twig\Attribute\As_Twig_Test;
use Twig\Extension\Extension_Interface;
use Twig\Extension\Runtime_Extension_Interface;
use Twig\Loader\Loader_Interface;
/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Twig_Extension extends Extension
{
    public function load(array $configs, Container_Builder $container): void
    {
        $loader = new Php_File_Loader($container, new File_Locator(__DIR__ . '/../Resources/config'));
        $loader->load('twig.php');
        if ($container::will_be_available('symfony/form', Form::class, ['symfony/twig-bundle'])) {
            $loader->load('form.php');
        }
        if ($container::will_be_available('symfony/console', Application::class, ['symfony/twig-bundle'])) {
            $loader->load('console.php');
        }
        if (!$container::will_be_available('symfony/translation', Translator::class, ['symfony/twig-bundle'])) {
            $container->remove_definition('twig.translation.extractor');
        }
        if ($container::will_be_available('symfony/validator', Constraint::class, ['symfony/twig-bundle'])) {
            $loader->load('validator.php');
        }
        foreach ($configs as $key => $config) {
            if (isset($config['globals'])) {
                foreach ($config['globals'] as $name => $value) {
                    if (\is_array($value) && isset($value['key'])) {
                        $configs[$key]['globals'][$name] = ['key' => $name, 'value' => $value];
                    }
                }
            }
        }
        $configuration = $this->get_configuration($configs, $container);
        $config = $this->process_configuration($configuration, $configs);
        if ($container::will_be_available('symfony/mailer', Mailer::class, ['symfony/twig-bundle'])) {
            $loader->load('mailer.php');
            if ($html_to_text_converter = $config['mailer']['html_to_text_converter'] ?? null) {
                $container->get_definition('twig.mime_body_renderer')->set_argument('$converter', new Reference($html_to_text_converter));
            }
            if (Container_Builder::will_be_available('symfony/translation', Locale_Switcher::class, ['symfony/framework-bundle'])) {
                $container->get_definition('twig.mime_body_renderer')->set_argument('$localeSwitcher', new Reference('translation.locale_switcher', Container_Builder::IGNORE_ON_INVALID_REFERENCE));
            }
        }
        if ($container::will_be_available('symfony/asset-mapper', Asset_Mapper::class, ['symfony/twig-bundle'])) {
            $loader->load('importmap.php');
        }
        $container->set_parameter('twig.form.resources', $config['form_themes']);
        $container->set_parameter('twig.default_path', $config['default_path']);
        $default_twig_path = $container->get_parameter_bag()->resolve_value($config['default_path']);
        $env_configurator_definition = $container->get_definition('twig.configurator.environment');
        $env_configurator_definition->replace_argument(0, $config['date']['format']);
        $env_configurator_definition->replace_argument(1, $config['date']['interval_format']);
        $env_configurator_definition->replace_argument(2, $config['date']['timezone']);
        $env_configurator_definition->replace_argument(3, $config['number_format']['decimals']);
        $env_configurator_definition->replace_argument(4, $config['number_format']['decimal_point']);
        $env_configurator_definition->replace_argument(5, $config['number_format']['thousands_separator']);
        $twig_filesystem_loader_definition = $container->get_definition('twig.loader.native_filesystem');
        // register user-configured paths
        foreach ($config['paths'] as $path => $namespace) {
            if (!$namespace) {
                $twig_filesystem_loader_definition->add_method_call('addPath', [$path]);
            } else {
                $twig_filesystem_loader_definition->add_method_call('addPath', [$path, $namespace]);
            }
        }
        // paths are modified in ExtensionPass if forms are enabled
        $container->get_definition('twig.template_iterator')->replace_argument(1, $config['paths']);
        $container->get_definition('twig.template_iterator')->replace_argument(3, $config['file_name_pattern']);
        if ($container->has_definition('twig.command.lint')) {
            $container->get_definition('twig.command.lint')->replace_argument(1, $config['file_name_pattern'] ?: ['*.twig']);
        }
        foreach ($this->get_bundle_template_paths($container, $config) as $name => $paths) {
            $namespace = $this->normalize_bundle_name($name);
            foreach ($paths as $path) {
                $twig_filesystem_loader_definition->add_method_call('addPath', [$path, $namespace]);
            }
            if ($paths) {
                // the last path must be the bundle views directory
                $twig_filesystem_loader_definition->add_method_call('addPath', [$path, '!' . $namespace]);
            }
        }
        if (file_exists($default_twig_path)) {
            $twig_filesystem_loader_definition->add_method_call('addPath', [$default_twig_path]);
        }
        $container->add_resource(new File_Existence_Resource($default_twig_path));
        if (!empty($config['globals'])) {
            $def = $container->get_definition('twig');
            foreach ($config['globals'] as $key => $global) {
                if (isset($global['type']) && 'service' === $global['type']) {
                    $def->add_method_call('addGlobal', [$key, new Reference($global['id'])]);
                } else {
                    $def->add_method_call('addGlobal', [$key, $global['value']]);
                }
            }
        }
        if (true === $config['cache']) {
            $auto_reload_or_default = $container->get_parameter_bag()->resolve_value($config['auto_reload'] ?? $config['debug']);
            $build_dir = $container->get_parameter('kernel.build_dir');
            $cache_dir = $container->get_parameter('kernel.cache_dir');
            if ($auto_reload_or_default || $cache_dir === $build_dir) {
                $config['cache'] = '%kernel.cache_dir%/twig';
            }
        }
        if (true === $config['cache']) {
            $config['cache'] = new Reference('twig.template_cache.chain');
        } else {
            $container->remove_definition('twig.template_cache.chain');
            $container->remove_definition('twig.template_cache.runtime_cache');
            $container->remove_definition('twig.template_cache.readonly_cache');
            $container->remove_definition('twig.template_cache.warmup_cache');
            if (false === $config['cache']) {
                $container->remove_definition('twig.template_cache_warmer');
            } else {
                $container->get_definition('twig.template_cache_warmer')->replace_argument(2, null);
            }
        }
        if (isset($config['autoescape_service'])) {
            $config['autoescape'] = [new Reference($config['autoescape_service']), $config['autoescape_service_method'] ?? '__invoke'];
        } else {
            $config['autoescape'] = 'name';
        }
        $container->get_definition('twig')->replace_argument(1, array_intersect_key($config, ['debug' => true, 'charset' => true, 'strict_variables' => true, 'autoescape' => true, 'cache' => true, 'auto_reload' => true, 'optimizations' => true]));
        $container->register_for_autoconfiguration(Extension_Interface::class)->add_tag('twig.extension');
        $container->register_for_autoconfiguration(Loader_Interface::class)->add_tag('twig.loader');
        $container->register_for_autoconfiguration(Runtime_Extension_Interface::class)->add_tag('twig.runtime');
        $container->register_attribute_for_autoconfiguration(As_Twig_Filter::class, Attribute_Extension_Pass::autoconfigure_from_attribute(...));
        $container->register_attribute_for_autoconfiguration(As_Twig_Function::class, Attribute_Extension_Pass::autoconfigure_from_attribute(...));
        $container->register_attribute_for_autoconfiguration(As_Twig_Test::class, Attribute_Extension_Pass::autoconfigure_from_attribute(...));
    }
    private function get_bundle_template_paths(Container_Builder $container, array $config): array
    {
        $bundle_hierarchy = [];
        foreach ($container->get_parameter('kernel.bundles_metadata') as $name => $bundle) {
            $default_override_bundle_path = $container->get_parameter_bag()->resolve_value($config['default_path']) . '/bundles/' . $name;
            if (file_exists($default_override_bundle_path)) {
                $bundle_hierarchy[$name][] = $default_override_bundle_path;
            }
            $container->add_resource(new File_Existence_Resource($default_override_bundle_path));
            if (file_exists($dir = $bundle['path'] . '/Resources/views') || file_exists($dir = $bundle['path'] . '/templates')) {
                $bundle_hierarchy[$name][] = $dir;
            }
            $container->add_resource(new File_Existence_Resource($dir));
        }
        return $bundle_hierarchy;
    }
    private function normalize_bundle_name(string $name): string
    {
        if (str_ends_with($name, 'Bundle')) {
            return substr($name, 0, -6);
        }
        return $name;
    }
}