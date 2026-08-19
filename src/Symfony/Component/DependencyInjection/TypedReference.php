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

namespace Symfony\Component\DependencyInjection;

/**
 * Represents a PHP type-hinted service reference.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TypedReference extends Reference
{
    private readonly ?string $name;

    /**
     * @param string      $id              The service identifier
     * @param string      $type            The PHP type of the identified service
     * @param int         $invalidBehavior The behavior when the service does not exist
     * @param string|null $name            The name of the argument targeting the service
     * @param array       $attributes      The attributes to be used
     */
    public function __construct(
        string|Reference $id,
        private readonly string $type,
        int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
        string|int|null $name = null,
        private readonly array $attributes = [],
    ) {
        $id = (string) $id;
        $this->name = $type === $id ? (null !== $name ? (string) $name : null) : null;
        parent::__construct($id, $invalidBehavior);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
