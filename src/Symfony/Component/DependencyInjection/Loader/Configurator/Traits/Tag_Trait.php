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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator\Traits;

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
trait Tag_Trait
{
    /**
     * Adds a tag for this definition.
     *
     * @return $this
     */
    final public function tag(string $name, array $attributes = []): static
    {
        if ('' === $name) {
            throw new InvalidArgumentException(\sprintf('The tag name for service "%s" must be a non-empty string.', $this->id));
        }
        $this->validate_attributes($name, $attributes);
        $this->definition->add_tag($name, $attributes);
        return $this;
    }
    /**
     * Adds a resource tag for this definition.
     *
     * @return $this
     */
    final public function resource_tag(string $name, array $attributes = []): static
    {
        if ('' === $name) {
            throw new InvalidArgumentException(\sprintf('The resource tag name for service "%s" must be a non-empty string.', $this->id));
        }
        $this->validate_attributes($name, $attributes);
        $this->definition->add_resource_tag($name, $attributes);
        return $this;
    }
    private function validate_attributes(string $tag, array $attributes, array $path = []): void
    {
        foreach ($attributes as $name => $value) {
            if (\is_array($value)) {
                $this->validate_attributes($tag, $value, [...$path, $name]);
            } elseif (!\is_scalar($value ?? '')) {
                $name = implode('.', [...$path, $name]);
                throw new InvalidArgumentException(\sprintf('A tag attribute must be of a scalar-type or an array of scalar-types for service "%s", tag "%s", attribute "%s".', $this->id, $tag, $name));
            }
        }
    }
}