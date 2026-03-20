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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Defaults_Configurator extends Abstract_Service_Configurator
{
    use Traits\Autoconfigure_Trait;
    use Traits\Autowire_Trait;
    use Traits\Bind_Trait;
    use Traits\Public_Trait;
    public const FACTORY = 'defaults';
    public function __construct(Services_Configurator $parent, Definition $definition, private ?string $path = null)
    {
        parent::__construct($parent, $definition);
    }
    /**
     * Adds a tag for this definition.
     *
     * @return $this
     *
     * @throws InvalidArgumentException when an invalid tag name or attribute is provided
     */
    final public function tag(string $name, array $attributes = []): static
    {
        if ('' === $name) {
            throw new InvalidArgumentException('The tag name in "_defaults" must be a non-empty string.');
        }
        $this->validate_attributes($name, $attributes);
        $this->definition->add_tag($name, $attributes);
        return $this;
    }
    /**
     * Adds a resource tag for this definition.
     *
     * @return $this
     *
     * @throws InvalidArgumentException when an invalid tag name or attribute is provided
     */
    final public function resource_tag(string $name, array $attributes = []): static
    {
        if ('' === $name) {
            throw new InvalidArgumentException('The resource tag name in "_defaults" must be a non-empty string.');
        }
        $this->validate_attributes($name, $attributes);
        $this->definition->add_resource_tag($name, $attributes);
        return $this;
    }
    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     */
    final public function instanceof(string $fqcn): Instanceof_Configurator
    {
        return $this->parent->instanceof($fqcn);
    }
    private function validate_attributes(string $tag, array $attributes, array $path = []): void
    {
        foreach ($attributes as $name => $value) {
            if (\is_array($value)) {
                $this->validate_attributes($tag, $value, [...$path, $name]);
            } elseif (!\is_scalar($value ?? '')) {
                $name = implode('.', [...$path, $name]);
                throw new InvalidArgumentException(\sprintf('Tag "%s", attribute "%s" in "_defaults" must be of a scalar-type or an array of scalar-type.', $tag, $name));
            }
        }
    }
}