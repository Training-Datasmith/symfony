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
namespace Symfony\Component\Dom_Crawler\Field;

/**
 * ChoiceFormField represents a choice form field.
 *
 * It is constructed from an HTML select tag, or an HTML checkbox, or radio inputs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Choice_Form_Field extends Form_Field
{
    private string $type;
    private bool $multiple;
    private array $options;
    private bool $validation_disabled = false;
    /**
     * Returns true if the field should be included in the submitted values.
     *
     * @return bool true if the field should be included in the submitted values, false otherwise
     */
    public function has_value(): bool
    {
        // don't send a value for unchecked checkboxes
        if (\in_array($this->type, ['checkbox', 'radio'], true) && null === $this->value) {
            return false;
        }
        return true;
    }
    /**
     * Check if the current selected option is disabled.
     */
    public function is_disabled(): bool
    {
        if ('checkbox' === $this->type) {
            return parent::is_disabled();
        }
        if (parent::is_disabled() && 'select' === $this->type) {
            return true;
        }
        foreach ($this->options as $option) {
            if ($option['value'] == $this->value && $option['disabled']) {
                return true;
            }
        }
        return false;
    }
    /**
     * Sets the value of the field.
     */
    public function select(string|array|bool $value): void
    {
        $this->set_value($value);
    }
    /**
     * Ticks a checkbox.
     *
     * @throws \LogicException When the type provided is not correct
     */
    public function tick(): void
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot tick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }
        $this->set_value(true);
    }
    /**
     * Unticks a checkbox.
     *
     * @throws \LogicException When the type provided is not correct
     */
    public function untick(): void
    {
        if ('checkbox' !== $this->type) {
            throw new \LogicException(\sprintf('You cannot untick "%s" as it is not a checkbox (%s).', $this->name, $this->type));
        }
        $this->set_value(false);
    }
    /**
     * Sets the value of the field.
     *
     * @throws \InvalidArgumentException When value type provided is not correct
     */
    public function set_value(string|array|bool|null $value): void
    {
        if ('checkbox' === $this->type && false === $value) {
            // uncheck
            $this->value = null;
        } elseif ('checkbox' === $this->type && true === $value) {
            // check
            $this->value = $this->options[0]['value'];
        } else {
            if (\is_array($value)) {
                if (!$this->multiple) {
                    throw new \InvalidArgumentException(\sprintf('The value for "%s" cannot be an array.', $this->name));
                }
                foreach ($value as $v) {
                    if (!$this->contains_option($v, $this->options)) {
                        throw new \InvalidArgumentException(\sprintf('Input "%s" cannot take "%s" as a value (possible values: "%s").', $this->name, $v, implode('", "', $this->available_option_values())));
                    }
                }
            } elseif (!$this->contains_option($value, $this->options)) {
                throw new \InvalidArgumentException(\sprintf('Input "%s" cannot take "%s" as a value (possible values: "%s").', $this->name, $value, implode('", "', $this->available_option_values())));
            }
            if ($this->multiple) {
                $value = (array) $value;
            }
            if (\is_array($value)) {
                $this->value = $value;
            } else {
                parent::set_value($value);
            }
        }
    }
    /**
     * Adds a choice to the current ones.
     *
     * @throws \LogicException When choice provided is neither multiple, radio nor select
     */
    final public function add_choice(\Dom_Element $node): void
    {
        if (!$this->multiple && !\in_array($this->type, ['radio', 'select'], true)) {
            throw new \LogicException(\sprintf('Unable to add a choice for "%s" as it is neither multiple, a radio button nor a select field.', $this->name));
        }
        $option = $this->build_option_value($node);
        $this->options[] = $option;
        if ($node->has_attribute('select' === $this->type ? 'selected' : 'checked')) {
            $this->value = $option['value'];
        }
    }
    /**
     * Returns the type of the choice field (radio, select, or checkbox).
     */
    public function get_type(): string
    {
        return $this->type;
    }
    /**
     * Returns true if the field accepts multiple values.
     */
    public function is_multiple(): bool
    {
        return $this->multiple;
    }
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('input' !== $this->node->node_name && 'select' !== $this->node->node_name) {
            throw new \LogicException(\sprintf('A ChoiceFormField can only be created from an input or select tag (%s given).', $this->node->node_name));
        }
        if ('input' === $this->node->node_name && 'checkbox' !== strtolower($this->node->get_attribute('type')) && 'radio' !== strtolower($this->node->get_attribute('type'))) {
            throw new \LogicException(\sprintf('A ChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is "%s").', $this->node->get_attribute('type')));
        }
        $this->value = null;
        $this->options = [];
        $this->multiple = false;
        if ('input' == $this->node->node_name) {
            $this->type = strtolower($this->node->get_attribute('type'));
            $option_value = $this->build_option_value($this->node);
            $this->options[] = $option_value;
            if ($this->node->has_attribute('checked')) {
                $this->value = $option_value['value'];
            }
        } else {
            $this->type = 'select';
            if ($this->node->has_attribute('multiple')) {
                $this->multiple = true;
                $this->value = [];
                $this->name = str_replace('[]', '', $this->name);
            }
            $found = false;
            foreach ($this->xpath->query('descendant::option', $this->node) as $option) {
                $option_value = $this->build_option_value($option);
                $this->options[] = $option_value;
                if ($option->has_attribute('selected')) {
                    $found = true;
                    if ($this->multiple) {
                        $this->value[] = $option_value['value'];
                    } else {
                        $this->value = $option_value['value'];
                    }
                }
            }
            // if no option is selected and if it is a simple select box, take the first option as the value
            if (!$found && !$this->multiple && $this->options) {
                $this->value = $this->options[0]['value'];
            }
        }
    }
    /**
     * Returns option value with associated disabled flag.
     */
    private function build_option_value(\Dom_Element $node): array
    {
        $option = [];
        $default_default_value = 'select' === $this->node->node_name ? '' : 'on';
        $default_value = isset($node->node_value) && $node->node_value ? $node->node_value : $default_default_value;
        $option['value'] = $node->has_attribute('value') ? $node->get_attribute('value') : $default_value;
        $option['disabled'] = $node->has_attribute('disabled');
        return $option;
    }
    /**
     * Checks whether given value is in the existing options.
     *
     * @internal
     */
    public function contains_option(string $option_value, array $options): bool
    {
        if ($this->validation_disabled) {
            return true;
        }
        foreach ($options as $option) {
            if ($option['value'] == $option_value) {
                return true;
            }
        }
        return false;
    }
    /**
     * Returns list of available field options.
     *
     * @internal
     */
    public function available_option_values(): array
    {
        $values = [];
        foreach ($this->options as $option) {
            $values[] = $option['value'];
        }
        return $values;
    }
    /**
     * Disables the internal validation of the field.
     *
     * @internal
     *
     * @return $this
     */
    public function disable_validation(): static
    {
        $this->validation_disabled = true;
        return $this;
    }
}