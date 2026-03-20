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
namespace Symfony\Component\Form\Extension\Validator\Violation_Mapper;

use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Property_Access\Property_Path;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<int, string>
 */
class Violation_Path implements \IteratorAggregate, Property_Path_Interface
{
    /** @var list<string> */
    private array $elements = [];
    private array $is_index = [];
    private array $maps_form = [];
    private string $path_as_string = '';
    private int $length = 0;
    /**
     * Creates a new violation path from a string.
     *
     * @param string $violationPath The property path of a {@link \Symfony\Component\Validator\ConstraintViolation} object
     */
    public function __construct(string $violation_path)
    {
        $path = new Property_Path($violation_path);
        $elements = $path->get_elements();
        $data = false;
        for ($i = 0, $l = \count($elements); $i < $l; ++$i) {
            if (!$data) {
                // The element "data" has not yet been passed
                if ('children' === $elements[$i] && $path->is_property($i)) {
                    // Skip element "children"
                    ++$i;
                    // Next element must exist and must be an index
                    // Otherwise consider this the end of the path
                    if ($i >= $l || !$path->is_index($i)) {
                        break;
                    }
                    // All the following index items (regardless if .children is
                    // explicitly used) are children and grand-children
                    for (; $i < $l && $path->is_index($i); ++$i) {
                        $this->elements[] = $elements[$i];
                        $this->is_index[] = true;
                        $this->maps_form[] = true;
                    }
                    // Rewind the pointer as the last element above didn't match
                    // (even if the pointer was moved forward)
                    --$i;
                } elseif ('data' === $elements[$i] && $path->is_property($i)) {
                    // Skip element "data"
                    ++$i;
                    // End of path
                    if ($i >= $l) {
                        break;
                    }
                    $this->elements[] = $elements[$i];
                    $this->is_index[] = $path->is_index($i);
                    $this->maps_form[] = false;
                    $data = true;
                } else {
                    // Neither "children" nor "data" property found
                    // Consider this the end of the path
                    break;
                }
            } else {
                // Already after the "data" element
                // Pick everything as is
                $this->elements[] = $elements[$i];
                $this->is_index[] = $path->is_index($i);
                $this->maps_form[] = false;
            }
        }
        $this->length = \count($this->elements);
        $this->build_string();
    }
    public function __toString(): string
    {
        return $this->path_as_string;
    }
    public function get_length(): int
    {
        return $this->length;
    }
    public function get_parent(): ?Property_Path_Interface
    {
        if ($this->length <= 1) {
            return null;
        }
        $parent = clone $this;
        --$parent->length;
        array_pop($parent->elements);
        array_pop($parent->is_index);
        array_pop($parent->maps_form);
        $parent->build_string();
        return $parent;
    }
    public function get_elements(): array
    {
        return $this->elements;
    }
    public function get_element(int $index): string
    {
        if (!isset($this->elements[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the violation path.', $index));
        }
        return $this->elements[$index];
    }
    public function is_property(int $index): bool
    {
        if (!isset($this->is_index[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the violation path.', $index));
        }
        return !$this->is_index[$index];
    }
    public function is_index(int $index): bool
    {
        if (!isset($this->is_index[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the violation path.', $index));
        }
        return $this->is_index[$index];
    }
    public function is_null_safe(int $index): bool
    {
        return false;
    }
    /**
     * Returns whether an element maps directly to a form.
     *
     * Consider the following violation path:
     *
     *     children[address].children[office].data.street
     *
     * In this example, "address" and "office" map to forms, while
     * "street does not.
     *
     * @throws OutOfBoundsException if the offset is invalid
     */
    public function maps_form(int $index): bool
    {
        if (!isset($this->maps_form[$index])) {
            throw new OutOfBoundsException(\sprintf('The index "%s" is not within the violation path.', $index));
        }
        return $this->maps_form[$index];
    }
    /**
     * Returns a new iterator for this path.
     */
    public function getIterator(): Violation_Path_Iterator
    {
        return new Violation_Path_Iterator($this);
    }
    /**
     * Builds the string representation from the elements.
     */
    private function build_string(): void
    {
        $this->path_as_string = '';
        $data = false;
        foreach ($this->elements as $index => $element) {
            if ($this->maps_form[$index]) {
                $this->path_as_string .= ".children[{$element}]";
            } elseif (!$data) {
                $this->path_as_string .= '.data' . ($this->is_index[$index] ? "[{$element}]" : ".{$element}");
                $data = true;
            } else {
                $this->path_as_string .= $this->is_index[$index] ? "[{$element}]" : ".{$element}";
            }
        }
        if ('' !== $this->path_as_string) {
            // remove leading dot
            $this->path_as_string = substr($this->path_as_string, 1);
        }
    }
}