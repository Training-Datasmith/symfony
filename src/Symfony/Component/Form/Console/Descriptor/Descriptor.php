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
namespace Symfony\Component\Form\Console\Descriptor;

use Symfony\Component\Console\Descriptor\Descriptor_Interface;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Output_Style;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Form\Resolved_Form_Type_Interface;
use Symfony\Component\Form\Util\Options_Resolver_Wrapper;
use Symfony\Component\Options_Resolver\Debug\Options_Resolver_Introspector;
use Symfony\Component\Options_Resolver\Exception\No_Configuration_Exception;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
abstract class Descriptor implements Descriptor_Interface
{
    protected Output_Style $output;
    protected array $own_options = [];
    protected array $overridden_options = [];
    protected array $parent_options = [];
    protected array $extension_options = [];
    protected array $required_options = [];
    protected array $parents = [];
    protected array $extensions = [];
    public function describe(Output_Interface $output, ?object $object, array $options = []): void
    {
        $this->output = $output instanceof Output_Style ? $output : new Symfony_Style(new Array_Input([]), $output);
        match (true) {
            null === $object => $this->describe_defaults($options),
            $object instanceof Resolved_Form_Type_Interface => $this->describe_resolved_form_type($object, $options),
            $object instanceof Options_Resolver => $this->describe_option($object, $options),
            default => throw new \InvalidArgumentException(\sprintf('Object of type "%s" is not describable.', get_debug_type($object))),
        };
    }
    abstract protected function describe_defaults(array $options): void;
    abstract protected function describe_resolved_form_type(Resolved_Form_Type_Interface $resolved_form_type, array $options = []): void;
    abstract protected function describe_option(Options_Resolver $options_resolver, array $options): void;
    protected function collect_options(Resolved_Form_Type_Interface $type): void
    {
        $this->parents = [];
        $this->extensions = [];
        if (null !== $type->get_parent()) {
            $options_resolver = clone $this->get_parent_options_resolver($type->get_parent());
        } else {
            $options_resolver = new Options_Resolver();
        }
        $type->get_inner_type()->configure_options($own_options_resolver = new Options_Resolver_Wrapper());
        $this->own_options = array_diff($own_options_resolver->get_defined_options(), $options_resolver->get_defined_options());
        $overridden_options = array_intersect(array_merge($own_options_resolver->get_defined_options(), $own_options_resolver->get_undefined_options()), $options_resolver->get_defined_options());
        $this->parent_options = [];
        foreach ($this->parents as $class => $parent_options) {
            $this->overridden_options[$class] = array_intersect($overridden_options, $parent_options);
            $this->parent_options[$class] = array_diff($parent_options, $overridden_options);
        }
        $type->get_inner_type()->configure_options($options_resolver);
        $this->collect_type_extensions_options($type, $options_resolver);
        $this->extension_options = [];
        foreach ($this->extensions as $class => $extension_options) {
            $this->overridden_options[$class] = array_intersect($overridden_options, $extension_options);
            $this->extension_options[$class] = array_diff($extension_options, $overridden_options);
        }
        $this->overridden_options = array_filter($this->overridden_options);
        $this->parent_options = array_filter($this->parent_options);
        $this->extension_options = array_filter($this->extension_options);
        $this->required_options = $options_resolver->get_required_options();
        $this->parents = array_keys($this->parents);
        $this->extensions = array_keys($this->extensions);
    }
    protected function get_option_definition(Options_Resolver $options_resolver, string $option): array
    {
        $definition = [];
        if ($info = $options_resolver->get_info($option)) {
            $definition = ['info' => $info];
        }
        $definition += ['required' => $options_resolver->is_required($option), 'deprecated' => $options_resolver->is_deprecated($option)];
        $introspector = new Options_Resolver_Introspector($options_resolver);
        $map = ['default' => 'getDefault', 'lazy' => 'getLazyClosures', 'allowedTypes' => 'getAllowedTypes', 'allowedValues' => 'getAllowedValues', 'normalizers' => 'getNormalizers', 'deprecation' => 'getDeprecation', 'nestedOptions' => 'getNestedOptions'];
        foreach ($map as $key => $method) {
            try {
                $definition[$key] = $introspector->{$method}($option);
            } catch (No_Configuration_Exception) {
                // noop
            }
        }
        if (isset($definition['deprecation']['message']) && \is_string($definition['deprecation']['message'])) {
            $definition['deprecationMessage'] = strtr($definition['deprecation']['message'], ['%name%' => $option]);
            $definition['deprecationPackage'] = $definition['deprecation']['package'];
            $definition['deprecationVersion'] = $definition['deprecation']['version'];
        }
        return $definition;
    }
    protected function filter_options_by_deprecated(Resolved_Form_Type_Interface $type): void
    {
        $deprecated_options = [];
        $resolver = $type->get_options_resolver();
        foreach ($resolver->get_defined_options() as $option) {
            if ($resolver->is_deprecated($option)) {
                $deprecated_options[] = $option;
            }
        }
        $filter_by_deprecated = static function (array $options) use ($deprecated_options): array {
            foreach ($options as $class => $opts) {
                if ($deprecated = array_intersect($deprecated_options, $opts)) {
                    $options[$class] = $deprecated;
                } else {
                    unset($options[$class]);
                }
            }
            return $options;
        };
        $this->own_options = array_intersect($deprecated_options, $this->own_options);
        $this->overridden_options = $filter_by_deprecated($this->overridden_options);
        $this->parent_options = $filter_by_deprecated($this->parent_options);
        $this->extension_options = $filter_by_deprecated($this->extension_options);
    }
    private function get_parent_options_resolver(Resolved_Form_Type_Interface $type): Options_Resolver
    {
        $this->parents[$class = $type->get_inner_type()::class] = [];
        if (null !== $type->get_parent()) {
            $options_resolver = clone $this->get_parent_options_resolver($type->get_parent());
        } else {
            $options_resolver = new Options_Resolver();
        }
        $inherited_options = $options_resolver->get_defined_options();
        $type->get_inner_type()->configure_options($options_resolver);
        $this->parents[$class] = array_diff($options_resolver->get_defined_options(), $inherited_options);
        $this->collect_type_extensions_options($type, $options_resolver);
        return $options_resolver;
    }
    private function collect_type_extensions_options(Resolved_Form_Type_Interface $type, Options_Resolver $options_resolver): void
    {
        foreach ($type->get_type_extensions() as $extension) {
            $inherited_options = $options_resolver->get_defined_options();
            $extension->configure_options($options_resolver);
            $this->extensions[$extension::class] = array_diff($options_resolver->get_defined_options(), $inherited_options);
        }
    }
}