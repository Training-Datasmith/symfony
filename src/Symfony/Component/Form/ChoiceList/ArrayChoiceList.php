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
namespace Symfony\Component\Form\Choice_List;

/**
 * A list of choices with arbitrary data types.
 *
 * The user of this class is responsible for assigning string values to the
 * choices and for their uniqueness.
 * Both the choices and their values are passed to the constructor.
 * Each choice must have a corresponding value (with the same key) in
 * the values array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Array_Choice_List implements Choice_List_Interface
{
    protected array $choices;
    /**
     * The values indexed by the original keys.
     */
    protected array $structured_values;
    /**
     * The original keys of the choices array.
     */
    protected array $original_keys;
    protected ?\Closure $value_callback = null;
    /**
     * Creates a list with the given choices and values.
     *
     * The given choice array must have the same array keys as the value array.
     *
     * @param iterable      $choices The selectable choices
     * @param callable|null $value   The callable for creating the value
     *                               for a choice. If `null` is passed,
     *                               incrementing integers are used as
     *                               values
     */
    public function __construct(iterable $choices, ?callable $value = null)
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }
        if (null === $value && $this->castable_to_string($choices)) {
            $value = static fn($choice): string => false === $choice ? '0' : (string) $choice;
        }
        if (null !== $value) {
            // If a deterministic value generator was passed, use it later
            $this->value_callback = $value(...);
        } else {
            // Otherwise generate incrementing integers as values
            $value = static function () {
                static $i = 0;
                return $i++;
            };
        }
        // If the choices are given as recursive array (i.e. with explicit
        // choice groups), flatten the array. The grouping information is needed
        // in the view only.
        $this->flatten($choices, $value, $choices_by_values, $keys_by_values, $structured_values);
        $this->choices = $choices_by_values;
        $this->original_keys = $keys_by_values;
        $this->structured_values = $structured_values;
    }
    public function get_choices(): array
    {
        return $this->choices;
    }
    public function get_values(): array
    {
        return array_map(strval(...), array_keys($this->choices));
    }
    public function get_structured_values(): array
    {
        return $this->structured_values;
    }
    public function get_original_keys(): array
    {
        return $this->original_keys;
    }
    public function get_choices_for_values(array $values): array
    {
        $choices = [];
        foreach ($values as $i => $given_value) {
            if (\array_key_exists($given_value ?? '', $this->choices)) {
                $choices[$i] = $this->choices[$given_value];
            }
        }
        return $choices;
    }
    public function get_values_for_choices(array $choices): array
    {
        $values = [];
        // Use the value callback to compare choices by their values, if present
        if ($this->value_callback) {
            $given_values = [];
            foreach ($choices as $i => $given_choice) {
                $given_values[$i] = (string) ($this->value_callback)($given_choice);
            }
            return array_intersect($given_values, array_keys($this->choices));
        }
        // Otherwise compare choices by identity
        foreach ($choices as $i => $given_choice) {
            foreach ($this->choices as $value => $choice) {
                if ($choice === $given_choice) {
                    $values[$i] = (string) $value;
                    break;
                }
            }
        }
        return $values;
    }
    /**
     * Flattens an array into the given output variables.
     *
     * @param array      $choices          The array to flatten
     * @param callable   $value            The callable for generating choice values
     * @param array|null $choicesByValues  The flattened choices indexed by the
     *                                     corresponding values
     * @param array|null $keysByValues     The original keys indexed by the
     *                                     corresponding values
     * @param array|null $structuredValues The values indexed by the original keys
     *
     * @internal
     */
    protected function flatten(array $choices, callable $value, ?array &$choices_by_values, ?array &$keys_by_values, ?array &$structured_values): void
    {
        if (null === $choices_by_values) {
            $choices_by_values = [];
            $keys_by_values = [];
            $structured_values = [];
        }
        foreach ($choices as $key => $choice) {
            if (\is_array($choice)) {
                $this->flatten($choice, $value, $choices_by_values, $keys_by_values, $structured_values[$key]);
                continue;
            }
            $choice_value = (string) $value($choice);
            $choices_by_values[$choice_value] = $choice;
            $keys_by_values[$choice_value] = $key;
            $structured_values[$key] = $choice_value;
        }
    }
    /**
     * Checks whether the given choices can be cast to strings without
     * generating duplicates.
     * This method is responsible for preventing conflict between scalar values
     * and the empty value.
     */
    private function castable_to_string(array $choices, array &$cache = []): bool
    {
        foreach ($choices as $choice) {
            if (\is_array($choice)) {
                if (!$this->castable_to_string($choice, $cache)) {
                    return false;
                }
                continue;
            }
            if (!\is_scalar($choice)) {
                return false;
            }
            // prevent having false casted to the empty string by isset()
            $choice = false === $choice ? '0' : (string) $choice;
            if (isset($cache[$choice])) {
                return false;
            }
            $cache[$choice] = true;
        }
        return true;
    }
}