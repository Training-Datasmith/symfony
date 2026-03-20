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
namespace Symfony\Component\Dependency_Injection\Argument;

/**
 * Represents a collection of services found by tag name to lazily iterate over.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Tagged_Iterator_Argument extends Iterator_Argument
{
    private mixed $index_attribute = null;
    private ?string $default_index_method = null;
    private ?string $default_priority_method = null;
    private bool $needs_indexes = false;
    private array $exclude = [];
    private bool $exclude_self = true;
    /**
     * @param string      $tag            The name of the tag identifying the target services
     * @param string|null $indexAttribute The name of the attribute that defines the key referencing each service in the tagged collection
     * @param bool        $needsIndexes   Whether indexes are required and should be generated when computing the map
     * @param string[]    $exclude        Services to exclude from the iterator
     * @param bool        $excludeSelf    Whether to automatically exclude the referencing service from the iterator
     */
    public function __construct(private readonly string $tag, ?string $index_attribute = null, bool|string|null $needs_indexes = false, array|bool $exclude = [], bool|string|null $exclude_self = true)
    {
        parent::__construct([]);
        if (\func_num_args() > 5 || !\is_bool($needs_indexes) || !\is_array($exclude) || !\is_bool($exclude_self)) {
            [, , $default_index_method, $needs_indexes, $default_priority_method, $exclude, $exclude_self] = \func_get_args() + [2 => null, false, null, [], true];
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The $defaultIndexMethod and $defaultPriorityMethod arguments of tagged locators and iterators are deprecated, use the #[AsTaggedItem] attribute instead.');
        } else {
            $default_index_method = $default_priority_method = false;
        }
        if (null === $index_attribute && $needs_indexes) {
            $index_attribute = preg_match('/[^.]++$/', $tag, $m) ? $m[0] : $tag;
        }
        $this->index_attribute = $index_attribute;
        $this->default_index_method = $default_index_method ?: ($index_attribute ? 'getDefault' . str_replace(' ', '', ucwords((string) preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $index_attribute))) . 'Name' : null);
        $this->default_priority_method = $default_priority_method ?: ($index_attribute ? 'getDefault' . str_replace(' ', '', ucwords((string) preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $index_attribute))) . 'Priority' : null);
        $this->needs_indexes = $needs_indexes;
        $this->exclude = $exclude;
        $this->exclude_self = $exclude_self;
    }
    public function get_tag(): string
    {
        return $this->tag;
    }
    public function get_index_attribute(): ?string
    {
        return $this->index_attribute;
    }
    /**
     * @deprecated since Symfony 8.1, use the #[AsTaggedItem] attribute instead of default methods
     */
    public function get_default_index_method(): ?string
    {
        if (!\func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The "%s()" method is deprecated, use the #[AsTaggedItem] attribute instead of default methods.', __METHOD__);
        }
        return $this->default_index_method;
    }
    public function needs_indexes(): bool
    {
        return $this->needs_indexes;
    }
    /**
     * @deprecated since Symfony 8.1, use the #[AsTaggedItem] attribute instead of default methods
     */
    public function get_default_priority_method(): ?string
    {
        if (!\func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The "%s()" method is deprecated, use the #[AsTaggedItem] attribute instead of default methods.', __METHOD__);
        }
        return $this->default_priority_method;
    }
    public function get_exclude(): array
    {
        return $this->exclude;
    }
    public function exclude_self(): bool
    {
        return $this->exclude_self;
    }
}