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
namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Builder\Expr_Builder;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Config\Definition\Exception\Forbidden_Overwrite_Exception;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Config\Definition\Exception\Invalid_Type_Exception;
use Symfony\Component\Config\Definition\Exception\Unset_Key_Exception;
use Symfony\Component\Config\Exception\LogicException;
/**
 * The base node class.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class Base_Node implements Node_Interface
{
    public const DEFAULT_PATH_SEPARATOR = '.';
    private static array $placeholder_unique_prefixes = [];
    private static array $placeholders = [];
    protected string $name;
    protected array $normalization_closures = [];
    protected array $normalized_types = [];
    protected array $final_validation_closures = [];
    protected bool $allow_overwrite = true;
    protected bool $required = false;
    protected array $deprecation = [];
    protected array $equivalent_values = [];
    protected array $attributes = [];
    private mixed $handling_placeholder = null;
    /**
     * @throws \InvalidArgumentException if the name contains a period
     */
    public function __construct(?string $name, protected ?Node_Interface $parent = null, protected string $path_separator = self::DEFAULT_PATH_SEPARATOR)
    {
        if (str_contains($name = (string) $name, $path_separator)) {
            throw new \InvalidArgumentException('The name must not contain ".' . $path_separator . '".');
        }
        $this->name = $name;
    }
    /**
     * Register possible (dummy) values for a dynamic placeholder value.
     *
     * Matching configuration values will be processed with a provided value, one by one. After a provided value is
     * successfully processed the configuration value is returned as is, thus preserving the placeholder.
     *
     * @internal
     */
    public static function set_placeholder(string $placeholder, array $values): void
    {
        if (!$values) {
            throw new \InvalidArgumentException('At least one value must be provided.');
        }
        self::$placeholders[$placeholder] = $values;
    }
    /**
     * Adds a common prefix for dynamic placeholder values.
     *
     * Matching configuration values will be skipped from being processed and are returned as is, thus preserving the
     * placeholder. An exact match provided by {@see setPlaceholder()} might take precedence.
     *
     * @internal
     */
    public static function set_placeholder_unique_prefix(string $prefix): void
    {
        self::$placeholder_unique_prefixes[] = $prefix;
    }
    /**
     * Resets all current placeholders available.
     *
     * @internal
     */
    public static function reset_placeholders(): void
    {
        self::$placeholder_unique_prefixes = [];
        self::$placeholders = [];
    }
    public function set_attribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
    public function get_attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
    public function has_attribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
    public function get_attributes(): array
    {
        return $this->attributes;
    }
    public function set_attributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }
    public function remove_attribute(string $key): void
    {
        unset($this->attributes[$key]);
    }
    /**
     * Sets an info message.
     */
    public function set_info(string $info): void
    {
        $this->set_attribute('info', $info);
    }
    /**
     * Returns info message.
     */
    public function get_info(): ?string
    {
        return $this->get_attribute('info');
    }
    /**
     * Sets the example configuration for this node.
     */
    public function set_example(string|array $example): void
    {
        $this->set_attribute('example', $example);
    }
    /**
     * Retrieves the example configuration for this node.
     */
    public function get_example(): string|array|null
    {
        return $this->get_attribute('example');
    }
    /**
     * Adds an equivalent value.
     */
    public function add_equivalent_value(mixed $original_value, mixed $equivalent_value): void
    {
        $this->equivalent_values[] = [$original_value, $equivalent_value];
    }
    /**
     * Set this node as required.
     */
    public function set_required(bool $boolean): void
    {
        $this->required = $boolean;
    }
    /**
     * Sets this node as deprecated.
     *
     * You can use %node% and %path% placeholders in your message to display,
     * respectively, the node name and its complete path.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message the deprecation message to use
     */
    public function set_deprecated(string $package, string $version, string $message = 'The child node "%node%" at path "%path%" is deprecated.'): void
    {
        $this->deprecation = ['package' => $package, 'version' => $version, 'message' => $message];
    }
    /**
     * Sets if this node can be overridden.
     */
    public function set_allow_overwrite(bool $allow): void
    {
        $this->allow_overwrite = $allow;
    }
    /**
     * Sets the closures used for normalization.
     *
     * @param \Closure[] $closures An array of Closures used for normalization
     */
    public function set_normalization_closures(array $closures): void
    {
        $this->normalization_closures = $closures;
    }
    /**
     * Sets the list of types supported by normalization.
     *
     * @param list<ExprBuilder::TYPE_*> $types
     */
    public function set_normalized_types(array $types): void
    {
        $this->normalized_types = $types;
    }
    /**
     * Gets the list of types supported by normalization.
     *
     * @return list<ExprBuilder::TYPE_*>
     */
    public function get_normalized_types(): array
    {
        return $this->normalized_types;
    }
    /**
     * Sets the closures used for final validation.
     *
     * @param \Closure[] $closures An array of Closures used for final validation
     */
    public function set_final_validation_closures(array $closures): void
    {
        $this->final_validation_closures = $closures;
    }
    public function is_required(): bool
    {
        return $this->required;
    }
    /**
     * Checks if this node is deprecated.
     */
    public function is_deprecated(): bool
    {
        return (bool) $this->deprecation;
    }
    /**
     * @param string $node The configuration node name
     * @param string $path The path of the node
     *
     * @return array{package: string, version: string, message: string}
     */
    public function get_deprecation(string $node, string $path): array
    {
        if (!$this->deprecation) {
            throw new LogicException(\sprintf('The node "%s" is not deprecated.', $this->get_name()));
        }
        return ['package' => $this->deprecation['package'], 'version' => $this->deprecation['version'], 'message' => strtr($this->deprecation['message'], ['%node%' => $node, '%path%' => $path])];
    }
    /**
     * @internal
     */
    public function get_deprecation_message(?Node_Interface $parent = null): string
    {
        if (!$this->deprecation) {
            throw new LogicException(\sprintf('The node "%s" is not deprecated.', $this->get_name()));
        }
        $message = strtr($this->deprecation['message'], ['%node%' => $this->get_name(), '%path%' => ($parent ?? $this->parent ?? $this)->get_path()]);
        if ($this->deprecation['package'] || $this->deprecation['version']) {
            return \sprintf('Since %s %s: ', $this->deprecation['package'], $this->deprecation['version']) . $message;
        }
        return $message;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function get_path(): string
    {
        if (null !== $this->parent) {
            return $this->parent->get_path() . $this->path_separator . $this->name;
        }
        return $this->name;
    }
    final public function merge(mixed $left_side, mixed $right_side): mixed
    {
        // Only enforce cannotBeOverwritten when there's actually something to overwrite.
        // When leftSide is empty (initial population), this check doesn't apply.
        if (!$this->allow_overwrite && [] !== $left_side) {
            throw new Forbidden_Overwrite_Exception(\sprintf('Configuration path "%s" cannot be overwritten. You have to define all options for this path, and any of its sub-paths in one configuration section.', $this->get_path()));
        }
        if ($left_side !== $left_placeholders = self::resolve_placeholder_value($left_side)) {
            foreach ($left_placeholders as $left_placeholder) {
                $this->handling_placeholder = $left_side;
                try {
                    $this->merge($left_placeholder, $right_side);
                } finally {
                    $this->handling_placeholder = null;
                }
            }
            return $right_side;
        }
        if ($right_side !== $right_placeholders = self::resolve_placeholder_value($right_side)) {
            foreach ($right_placeholders as $right_placeholder) {
                $this->handling_placeholder = $right_side;
                try {
                    $this->merge($left_side, $right_placeholder);
                } finally {
                    $this->handling_placeholder = null;
                }
            }
            return $right_side;
        }
        $this->do_validate_type($left_side);
        $this->do_validate_type($right_side);
        return $this->merge_values($left_side, $right_side);
    }
    final public function normalize(mixed $value): mixed
    {
        $value = $this->pre_normalize($value);
        // run custom normalization closures
        foreach ($this->normalization_closures as $closure) {
            $value = $closure($value);
        }
        // resolve placeholder value
        if ($value !== $placeholders = self::resolve_placeholder_value($value)) {
            foreach ($placeholders as $placeholder) {
                $this->handling_placeholder = $value;
                try {
                    $this->normalize($placeholder);
                } finally {
                    $this->handling_placeholder = null;
                }
            }
            return $value;
        }
        // replace value with their equivalent
        foreach ($this->equivalent_values as $data) {
            if ($data[0] === $value) {
                $value = $data[1];
            }
        }
        // validate type
        $this->do_validate_type($value);
        // normalize value
        return $this->normalize_value($value);
    }
    /**
     * Normalizes the value before any other normalization is applied.
     */
    protected function pre_normalize(mixed $value): mixed
    {
        return $value;
    }
    /**
     * Returns parent node for this node.
     */
    public function get_parent(): ?Node_Interface
    {
        return $this->parent;
    }
    final public function finalize(mixed $value): mixed
    {
        if ($value !== $placeholders = self::resolve_placeholder_value($value)) {
            foreach ($placeholders as $placeholder) {
                $this->handling_placeholder = $value;
                try {
                    $this->finalize($placeholder);
                } finally {
                    $this->handling_placeholder = null;
                }
            }
            return $value;
        }
        $this->do_validate_type($value);
        $value = $this->finalize_value($value);
        // Perform validation on the final value if a closure has been set.
        // The closure is also allowed to return another value.
        foreach ($this->final_validation_closures as $closure) {
            try {
                $value = $closure($value);
            } catch (Exception $e) {
                if ($e instanceof Unset_Key_Exception && null !== $this->handling_placeholder) {
                    continue;
                }
                throw $e;
            } catch (\Exception $e) {
                throw new Invalid_Configuration_Exception(\sprintf('Invalid configuration for path "%s": ', $this->get_path()) . $e->get_message(), $e->get_code(), $e);
            }
        }
        return $value;
    }
    /**
     * Validates the type of a Node.
     *
     * @throws InvalidTypeException when the value is invalid
     */
    abstract protected function validate_type(mixed $value): void;
    /**
     * Normalizes the value.
     */
    abstract protected function normalize_value(mixed $value): mixed;
    /**
     * Merges two values together.
     */
    abstract protected function merge_values(mixed $left_side, mixed $right_side): mixed;
    /**
     * Finalizes a value.
     */
    abstract protected function finalize_value(mixed $value): mixed;
    /**
     * Tests if placeholder values are allowed for this node.
     */
    protected function allow_placeholders(): bool
    {
        return true;
    }
    /**
     * Tests if a placeholder is being handled currently.
     */
    protected function is_handling_placeholder(): bool
    {
        return null !== $this->handling_placeholder;
    }
    /**
     * Gets allowed dynamic types for this node.
     */
    protected function get_valid_placeholder_types(): array
    {
        return [];
    }
    private static function resolve_placeholder_value(mixed $value): mixed
    {
        if (\is_string($value)) {
            if (isset(self::$placeholders[$value])) {
                return self::$placeholders[$value];
            }
            foreach (self::$placeholder_unique_prefixes as $placeholder_unique_prefix) {
                if (str_starts_with($value, (string) $placeholder_unique_prefix)) {
                    return [];
                }
            }
        }
        return $value;
    }
    private function do_validate_type(mixed $value): void
    {
        if (null !== $this->handling_placeholder && !$this->allow_placeholders()) {
            $e = new Invalid_Type_Exception(\sprintf('A dynamic value is not compatible with a "%s" node type at path "%s".', static::class, $this->get_path()));
            $e->set_path($this->get_path());
            throw $e;
        }
        if (null === $this->handling_placeholder || null === $value) {
            $this->validate_type($value);
            return;
        }
        $known_types = array_keys(self::$placeholders[$this->handling_placeholder]);
        $valid_types = $this->get_valid_placeholder_types();
        if ($valid_types && array_diff($known_types, $valid_types)) {
            $e = new Invalid_Type_Exception(\sprintf('Invalid type for path "%s". Expected %s, but got %s.', $this->get_path(), 1 === \count($valid_types) ? '"' . reset($valid_types) . '"' : 'one of "' . implode('", "', $valid_types) . '"', 1 === \count($known_types) ? '"' . reset($known_types) . '"' : 'one of "' . implode('", "', $known_types) . '"'));
            if ($hint = $this->get_info()) {
                $e->add_hint($hint);
            }
            $e->set_path($this->get_path());
            throw $e;
        }
        $this->validate_type($value);
    }
}