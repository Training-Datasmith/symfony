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
namespace Symfony\Component\Http_Foundation;

// Help opcache.preload discover always-needed symbols
class_exists(Accept_Header_Item::class);
/**
 * Represents an Accept-* header.
 *
 * An accept header is compound with a list of items,
 * sorted by descending quality.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Accept_Header implements \Stringable
{
    /**
     * @var array<string, AcceptHeaderItem>
     */
    private array $items = [];
    private bool $sorted = true;
    /**
     * @param AcceptHeaderItem[] $items
     */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }
    /**
     * Builds an AcceptHeader instance from a string.
     */
    public static function from_string(?string $header_value): self
    {
        $items = [];
        foreach (Header_Utils::split($header_value ?? '', ',;=') as $i => $parts) {
            $part = array_shift($parts);
            $item = new Accept_Header_Item($part[0], Header_Utils::combine($parts));
            $items[] = $item->set_index($i);
        }
        return new self($items);
    }
    /**
     * Returns header value's string representation.
     */
    public function __toString(): string
    {
        return implode(',', $this->items);
    }
    /**
     * Tests if header has given value.
     */
    public function has(string $value): bool
    {
        $canonical_key = $this->get_canonical_key(Accept_Header_Item::from_string($value));
        return isset($this->items[$canonical_key]);
    }
    /**
     * Returns given value's item, if exists.
     */
    public function get(string $value): ?Accept_Header_Item
    {
        $query_item = Accept_Header_Item::from_string($value . ';q=1');
        $canonical_key = $this->get_canonical_key($query_item);
        if (isset($this->items[$canonical_key])) {
            return $this->items[$canonical_key];
        }
        // Collect and filter matching candidates
        if (!$candidates = array_filter($this->items, fn(Accept_Header_Item $item): bool => $this->matches($item, $query_item))) {
            return null;
        }
        usort($candidates, fn($a, $b): int => ($this->get_specificity($b, $query_item) <=> $this->get_specificity($a, $query_item) ?: $b->get_quality() <=> $a->get_quality()) ?: $a->get_index() <=> $b->get_index());
        return reset($candidates);
    }
    /**
     * Adds an item.
     *
     * @return $this
     */
    public function add(Accept_Header_Item $item): static
    {
        $this->items[$this->get_canonical_key($item)] = $item;
        $this->sorted = false;
        return $this;
    }
    /**
     * Returns all items.
     *
     * @return AcceptHeaderItem[]
     */
    public function all(): array
    {
        $this->sort();
        return $this->items;
    }
    /**
     * Filters items on their value using given regex.
     */
    public function filter(string $pattern): self
    {
        return new self(array_filter($this->items, static fn(\Symfony\Component\Http_Foundation\Accept_Header_Item $item): int|false => preg_match($pattern, $item->get_value())));
    }
    /**
     * Returns first item.
     */
    public function first(): ?Accept_Header_Item
    {
        $this->sort();
        return $this->items ? reset($this->items) : null;
    }
    /**
     * Sorts items by descending quality.
     */
    private function sort(): void
    {
        if (!$this->sorted) {
            uasort($this->items, static fn($a, $b): int => $b->get_quality() <=> $a->get_quality() ?: $a->get_index() <=> $b->get_index());
            $this->sorted = true;
        }
    }
    /**
     * Generates the canonical key for storing/retrieving an item.
     */
    private function get_canonical_key(Accept_Header_Item $item): string
    {
        $parts = [];
        // Normalize and sort attributes for consistent key generation
        $attributes = $this->get_media_params($item);
        ksort($attributes);
        foreach ($attributes as $name => $value) {
            if (null === $value) {
                $parts[] = $name;
                // Flag parameter (e.g., "flowed")
                continue;
            }
            // Quote values containing spaces, commas, semicolons, or equals per RFC 9110
            // This handles cases like 'format="value with space"' or similar.
            $quoted_value = \is_string($value) && preg_match('/[\s;,=]/', $value) ? '"' . addcslashes($value, '"\\') . '"' : $value;
            $parts[] = $name . '=' . $quoted_value;
        }
        return $item->get_value() . ($parts ? ';' . implode(';', $parts) : '');
    }
    /**
     * Checks if a given header item (range) matches a queried item (value).
     *
     * @param AcceptHeaderItem $rangeItem The item from the Accept header (e.g., text/*;format=flowed)
     * @param AcceptHeaderItem $queryItem The item being queried (e.g., text/plain;format=flowed;charset=utf-8)
     */
    private function matches(Accept_Header_Item $range_item, Accept_Header_Item $query_item): bool
    {
        $range_value = strtolower($range_item->get_value());
        $query_value = strtolower($query_item->get_value());
        // Handle universal wildcard ranges
        if ('*' === $range_value || '*/*' === $range_value) {
            return $this->range_parameters_match($range_item, $query_item);
        }
        // Queries for '*' only match wildcard ranges (handled above)
        if ('*' === $query_value) {
            return false;
        }
        // Ensure media vs. non-media consistency
        $is_query_media = str_contains($query_value, '/');
        $is_range_media = str_contains($range_value, '/');
        if ($is_query_media !== $is_range_media) {
            return false;
        }
        // Non-media: exact match only (wildcards handled above)
        if (!$is_query_media) {
            return $range_value === $query_value && $this->range_parameters_match($range_item, $query_item);
        }
        // Media type: type/subtype with wildcards
        [$query_type, $query_subtype] = explode('/', $query_value, 2);
        [$range_type, $range_subtype] = explode('/', $range_value, 2) + [1 => '*'];
        if ('*' !== $range_type && $range_type !== $query_type) {
            return false;
        }
        if ('*' !== $range_subtype && $range_subtype !== $query_subtype) {
            return false;
        }
        // Parameters must match
        return $this->range_parameters_match($range_item, $query_item);
    }
    /**
     * Checks if the parameters of a range item are satisfied by the query item.
     *
     * Parameters are case-insensitive; range params must be a subset of query params.
     */
    private function range_parameters_match(Accept_Header_Item $range_item, Accept_Header_Item $query_item): bool
    {
        $query_attributes = $this->get_media_params($query_item);
        $range_attributes = $this->get_media_params($range_item);
        foreach ($range_attributes as $name => $range_value) {
            if (!\array_key_exists($name, $query_attributes)) {
                return false;
                // Missing required param
            }
            $query_value = $query_attributes[$name];
            if (null === $range_value) {
                return null === $query_value;
                // Both flags or neither
            }
            if (null === $query_value || strtolower($query_value) !== strtolower($range_value)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Calculates a specificity score for sorting: media precision + param count.
     */
    private function get_specificity(Accept_Header_Item $item, Accept_Header_Item $query_item): int
    {
        $range_value = strtolower($item->get_value());
        $query_value = strtolower($query_item->get_value());
        $param_count = \count($this->get_media_params($item));
        $is_query_media = str_contains($query_value, '/');
        $is_range_media = str_contains($range_value, '/');
        if (!$is_query_media && !$is_range_media) {
            return ('*' !== $range_value ? 2000 : 1000) + $param_count;
        }
        [$range_type, $range_subtype] = explode('/', $range_value, 2) + [1 => '*'];
        $specificity = match (true) {
            '*' !== $range_subtype => 3000,
            // Exact subtype (text/plain)
            '*' !== $range_type => 2000,
            // Type wildcard (text/*)
            default => 1000,
        };
        return $specificity + $param_count;
    }
    /**
     * Returns normalized attributes: keys lowercased, excluding 'q'.
     */
    private function get_media_params(Accept_Header_Item $item): array
    {
        $attributes = array_change_key_case($item->get_attributes(), \CASE_LOWER);
        unset($attributes['q']);
        return $attributes;
    }
}