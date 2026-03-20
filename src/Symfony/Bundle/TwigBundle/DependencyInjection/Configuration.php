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

use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Mime\Html_To_Text_Converter\Html_To_Text_Converter_Interface;
/**
 * TwigExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration implements Configuration_Interface
{
    /**
     * Generates the configuration tree builder.
     */
    public function get_config_tree_builder(): Tree_Builder
    {
        $tree_builder = new Tree_Builder('twig');
        $root_node = $tree_builder->get_root_node();
        $root_node->doc_url('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/twig.html', 'symfony/twig-bundle');
        $this->add_form_themes_section($root_node);
        $this->add_globals_section($root_node);
        $this->add_twig_options($root_node);
        $this->add_twig_format_options($root_node);
        $this->add_mailer_section($root_node);
        return $tree_builder;
    }
    private function add_form_themes_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('form_themes', 'form_theme')->add_default_children_if_none_set()->prototype('scalar')->default_value('form_div_layout.html.twig')->end()->example(['@My/form.html.twig'])->validate()->if_true(static fn($v): bool => !\in_array('form_div_layout.html.twig', $v, true))->then(static fn($v): array => array_merge(['form_div_layout.html.twig'], $v))->end()->end()->end();
    }
    private function add_globals_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('globals', 'global')->normalize_keys(false)->use_attribute_as_key('key')->example(['foo' => '@bar', 'pi' => 3.14])->prototype('array')->normalize_keys(false)->before_normalization()->if_true(static fn($v): bool => \is_string($v) && str_starts_with($v, '@'))->then(static function ($v): string|array {
            if (str_starts_with($v, '@@')) {
                return substr($v, 1);
            }
            return ['id' => substr($v, 1), 'type' => 'service'];
        })->end()->before_normalization()->if_true(static function ($v): bool {
            if (\is_array($v)) {
                $keys = array_keys($v);
                sort($keys);
                return $keys !== ['id', 'type'] && $keys !== ['value'];
            }
            return true;
        })->then(static fn($v): array => ['value' => $v])->end()->children()->scalar_node('id')->end()->scalar_node('type')->validate()->if_not_in_array(['service'])->then_invalid('The %s type is not supported')->end()->end()->variable_node('value')->end()->end()->end()->end()->end();
    }
    private function add_twig_options(Array_Node_Definition $root_node): void
    {
        $root_node->children()->scalar_node('autoescape_service')->default_null()->end()->scalar_node('autoescape_service_method')->default_null()->end()->scalar_node('cache')->default_true()->end()->scalar_node('charset')->default_value('%kernel.charset%')->end()->boolean_node('debug')->default_value('%kernel.debug%')->end()->boolean_node('strict_variables')->default_value('%kernel.debug%')->end()->scalar_node('auto_reload')->end()->integer_node('optimizations')->min(-1)->end()->scalar_node('default_path')->info('The default path used to load templates.')->default_value('%kernel.project_dir%/templates')->end()->array_node('file_name_pattern')->example('*.twig')->info('Pattern of file name used for cache warmer and linter.')->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->array_node('paths', 'path')->normalize_keys(false)->use_attribute_as_key('paths')->before_normalization()->if_array()->then(static function ($paths): array {
            $normalized = [];
            foreach ($paths as $path => $namespace) {
                if (\is_array($namespace)) {
                    // xml
                    $path = $namespace['value'];
                    $namespace = $namespace['namespace'];
                }
                // path within the default namespace
                if (ctype_digit((string) $path)) {
                    $path = $namespace;
                    $namespace = null;
                }
                $normalized[$path] = $namespace;
            }
            return $normalized;
        })->end()->prototype('variable')->end()->end()->end();
    }
    private function add_twig_format_options(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('date')->info('The default format options used by the date filter.')->add_defaults_if_not_set()->children()->scalar_node('format')->default_value('F j, Y H:i')->end()->scalar_node('interval_format')->default_value('%d days')->end()->scalar_node('timezone')->info('The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used.')->default_null()->end()->end()->end()->array_node('number_format')->info('The default format options for the number_format filter.')->add_defaults_if_not_set()->children()->integer_node('decimals')->default_value(0)->end()->scalar_node('decimal_point')->default_value('.')->end()->scalar_node('thousands_separator')->default_value(',')->end()->end()->end()->end();
    }
    private function add_mailer_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('mailer')->children()->scalar_node('html_to_text_converter')->info(\sprintf('A service implementing the "%s".', Html_To_Text_Converter_Interface::class))->default_null()->end()->end()->end()->end();
    }
}