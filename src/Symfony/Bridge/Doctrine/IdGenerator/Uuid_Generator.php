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
namespace Symfony\Bridge\Doctrine\Id_Generator;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Id\Abstract_Id_Generator;
use Symfony\Component\Uid\Factory\Name_Based_Uuid_Factory;
use Symfony\Component\Uid\Factory\Random_Based_Uuid_Factory;
use Symfony\Component\Uid\Factory\Time_Based_Uuid_Factory;
use Symfony\Component\Uid\Factory\Uuid_Factory;
use Symfony\Component\Uid\Uuid;
final class Uuid_Generator extends Abstract_Id_Generator
{
    private readonly Uuid_Factory $proto_factory;
    private Uuid_Factory|Name_Based_Uuid_Factory|Random_Based_Uuid_Factory|Time_Based_Uuid_Factory $factory;
    private ?string $entity_getter = null;
    public function __construct(?Uuid_Factory $factory = null)
    {
        $this->proto_factory = $this->factory = $factory ?? new Uuid_Factory();
    }
    public function generate_id(Entity_Manager_Interface $em, $entity): Uuid
    {
        if (null !== $this->entity_getter) {
            if (\is_callable([$entity, $this->entity_getter])) {
                return $this->factory->create($entity->{$this->entity_getter}());
            }
            return $this->factory->create($entity->{$this->entity_getter});
        }
        return $this->factory->create();
    }
    public function name_based(string $entity_getter, Uuid|string|null $namespace = null): static
    {
        $clone = clone $this;
        $clone->factory = $clone->proto_factory->name_based($namespace);
        $clone->entity_getter = $entity_getter;
        return $clone;
    }
    public function random_based(): static
    {
        $clone = clone $this;
        $clone->factory = $clone->proto_factory->random_based();
        $clone->entity_getter = null;
        return $clone;
    }
    public function time_based(Uuid|string|null $node = null): static
    {
        $clone = clone $this;
        $clone->factory = $clone->proto_factory->time_based($node);
        $clone->entity_getter = null;
        return $clone;
    }
}