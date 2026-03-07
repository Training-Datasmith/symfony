<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class ClassMetadata implements ClassMetadataInterface
{
    /**
     * @var AttributeMetadataInterface[]
     */
    private array $attributesMetadata = [];

    private ?\ReflectionClass $reflClass = null;

    public function __construct(private readonly string $name, private ?ClassDiscriminatorMapping $classDiscriminatorMapping = null)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addAttributeMetadata(AttributeMetadataInterface $attributeMetadata): void
    {
        $this->attributesMetadata[$attributeMetadata->getName()] = $attributeMetadata;
    }

    public function getAttributesMetadata(): array
    {
        return $this->attributesMetadata;
    }

    public function merge(ClassMetadataInterface $classMetadata): void
    {
        foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
            if (isset($this->attributesMetadata[$attributeMetadata->getName()])) {
                $this->attributesMetadata[$attributeMetadata->getName()]->merge($attributeMetadata);
            } else {
                $this->addAttributeMetadata($attributeMetadata);
            }
        }
    }

    public function getReflectionClass(): \ReflectionClass
    {
        return $this->reflClass ??= new \ReflectionClass($this->getName());
    }

    public function getClassDiscriminatorMapping(): ?ClassDiscriminatorMapping
    {
        return $this->classDiscriminatorMapping;
    }

    public function setClassDiscriminatorMapping(?ClassDiscriminatorMapping $mapping): void
    {
        $this->classDiscriminatorMapping = $mapping;
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'attributesMetadata' => $this->attributesMetadata,
            'classDiscriminatorMapping' => $this->classDiscriminatorMapping,
        ];
    }
}
