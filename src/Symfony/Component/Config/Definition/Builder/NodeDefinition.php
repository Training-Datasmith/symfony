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
namespace Symfony\Component\Config\Definition\Builder;

use Composer\Installed_Versions;
use Symfony\Component\Config\Definition\Base_Node;
use Symfony\Component\Config\Definition\Exception\Invalid_Definition_Exception;
use Symfony\Component\Config\Definition\Node_Interface;
/**
 * This class provides a fluent interface for defining a node.
 *
 * @template TParent of NodeParentInterface|null = null
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class Node_Definition implements Node_Parent_Interface
{
    /**
     * @var NormalizationBuilder<$this>
     */
    protected Normalization_Builder $normalization;
    /**
     * @var ValidationBuilder<$this>
     */
    protected Validation_Builder $validation;
    protected mixed $default_value;
    protected bool $default = false;
    protected bool $required = false;
    protected array $deprecation = [];
    /**
     * @var MergeBuilder<$this>
     */
    protected Merge_Builder $merge;
    protected bool $allow_empty_value = true;
    protected mixed $null_equivalent = null;
    protected mixed $true_equivalent = true;
    protected mixed $false_equivalent = false;
    protected string $path_separator = Base_Node::DEFAULT_PATH_SEPARATOR;
    protected array $attributes = [];
    /**
     * @param TParent $parent
     */
    public function __construct(protected ?string $name, protected Node_Parent_Interface|Node_Interface|null $parent = null)
    {
    }
    /**
     * Sets the parent node.
     *
     * @template TNewParent of NodeParentInterface
     *
     * @psalm-this-out static<TNewParent>
     *
     * @return $this
     */
    public function set_parent(Node_Parent_Interface $parent): static
    {
        $this->parent = $parent;
        return $this;
    }
    /**
     * Sets info message.
     *
     * @return $this
     */
    public function info(string $info): static
    {
        return $this->attribute('info', $info);
    }
    /**
     * Sets example configuration.
     *
     * @return $this
     */
    public function example(string|array $example): static
    {
        return $this->attribute('example', $example);
    }
    /**
     * Sets the documentation URI, as usually put in the "@see" tag of a doc block. This
     * can either be a URL or a file path. You can use the placeholders {package},
     * {version:major} and {version:minor} in the URI.
     *
     * @return $this
     */
    public function doc_url(string $uri, ?string $package = null): static
    {
        if ($package) {
            preg_match('/^(\d+)\.(\d+)\.(\d+)/', Installed_Versions::get_version($package) ?? '', $m);
        }
        return $this->attribute('docUrl', strtr($uri, ['{package}' => $package ?? '', '{version:major}' => $m[1] ?? '', '{version:minor}' => $m[2] ?? '']));
    }
    /**
     * Sets an attribute on the node.
     *
     * @return $this
     */
    public function attribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    /**
     * Returns the parent node.
     *
     * @return TParent
     */
    public function end(): ?Node_Parent_Interface
    {
        return $this->parent;
    }
    /**
     * Creates the node.
     */
    public function get_node(bool $force_root_node = false): Node_Interface
    {
        if ($force_root_node) {
            $this->parent = null;
        }
        if (isset($this->normalization)) {
            $allowed_types = [];
            foreach ($this->normalization->before as $expr) {
                $allowed_types[] = $expr->allowed_types;
            }
            $allowed_types = array_unique($allowed_types);
            $this->normalization->before = Expr_Builder::build_expressions($this->normalization->before);
            $this->normalization->declared_types = $allowed_types;
        }
        if (isset($this->validation)) {
            $this->validation->rules = Expr_Builder::build_expressions($this->validation->rules);
        }
        $node = $this->create_node();
        if ($node instanceof Base_Node) {
            $node->set_attributes($this->attributes);
        }
        return $node;
    }
    /**
     * Sets the default value.
     *
     * @return $this
     */
    public function default_value(mixed $value): static
    {
        if ($this->required) {
            throw new Invalid_Definition_Exception(\sprintf('The node "%s" cannot be required and have a default value.', $this->name));
        }
        $this->default = true;
        $this->default_value = $value;
        return $this;
    }
    /**
     * Sets the node as required.
     *
     * @return $this
     */
    public function is_required(): static
    {
        if ($this->default) {
            throw new Invalid_Definition_Exception(\sprintf('The node "%s" cannot be required and have a default value.', $this->name));
        }
        $this->required = true;
        return $this;
    }
    /**
     * Sets the node as deprecated.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message the deprecation message to use
     *
     * You can use %node% and %path% placeholders in your message to display,
     * respectively, the node name and its complete path
     *
     * @return $this
     */
    public function set_deprecated(string $package, string $version, string $message = 'The child node "%node%" at path "%path%" is deprecated.'): static
    {
        $this->deprecation = ['package' => $package, 'version' => $version, 'message' => $message];
        return $this;
    }
    /**
     * Sets the equivalent value used when the node contains null.
     *
     * @return $this
     */
    public function treat_null_like(mixed $value): static
    {
        $this->null_equivalent = $value;
        return $this;
    }
    /**
     * Sets the equivalent value used when the node contains true.
     *
     * @return $this
     */
    public function treat_true_like(mixed $value): static
    {
        $this->true_equivalent = $value;
        return $this;
    }
    /**
     * Sets the equivalent value used when the node contains false.
     *
     * @return $this
     */
    public function treat_false_like(mixed $value): static
    {
        $this->false_equivalent = $value;
        return $this;
    }
    /**
     * Sets null as the default value.
     *
     * @return $this
     */
    public function default_null(): static
    {
        return $this->default_value(null);
    }
    /**
     * Sets true as the default value.
     *
     * @return $this
     */
    public function default_true(): static
    {
        return $this->default_value(true);
    }
    /**
     * Sets false as the default value.
     *
     * @return $this
     */
    public function default_false(): static
    {
        return $this->default_value(false);
    }
    /**
     * Sets an expression to run before the normalization.
     *
     * @return ExprBuilder<$this>
     */
    public function before_normalization(): Expr_Builder
    {
        return $this->normalization()->before();
    }
    /**
     * Denies the node value being empty.
     *
     * @return $this
     */
    public function cannot_be_empty(): static
    {
        $this->allow_empty_value = false;
        return $this;
    }
    /**
     * Sets an expression to run for the validation.
     *
     * The expression receives the value of the node and must return it. It can
     * modify it.
     * An exception should be thrown when the node is not valid.
     *
     * @return ExprBuilder<$this>
     */
    public function validate(): Expr_Builder
    {
        return $this->validation()->rule();
    }
    /**
     * Sets whether the node can be overwritten.
     *
     * @return $this
     */
    public function cannot_be_overwritten(bool $deny = true): static
    {
        $this->merge()->deny_overwrite($deny);
        return $this;
    }
    /**
     * Gets the builder for validation rules.
     *
     * @return ValidationBuilder<$this>
     */
    protected function validation(): Validation_Builder
    {
        return $this->validation ??= new Validation_Builder($this);
    }
    /**
     * Gets the builder for merging rules.
     *
     * @return MergeBuilder<$this>
     */
    protected function merge(): Merge_Builder
    {
        return $this->merge ??= new Merge_Builder($this);
    }
    /**
     * Gets the builder for normalization rules.
     *
     * @return NormalizationBuilder<$this>
     */
    protected function normalization(): Normalization_Builder
    {
        return $this->normalization ??= new Normalization_Builder($this);
    }
    /**
     * Instantiate and configure the node according to this definition.
     *
     * @throws InvalidDefinitionException When the definition is invalid
     */
    abstract protected function create_node(): Node_Interface;
    /**
     * Set PathSeparator to use.
     *
     * @return $this
     */
    public function set_path_separator(string $separator): static
    {
        if ($this instanceof Parent_Node_Definition_Interface) {
            foreach ($this->get_child_node_definitions() as $child) {
                $child->set_path_separator($separator);
            }
        }
        $this->path_separator = $separator;
        return $this;
    }
}