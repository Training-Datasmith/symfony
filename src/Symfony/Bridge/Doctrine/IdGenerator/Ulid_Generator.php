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
use Symfony\Component\Uid\Factory\Ulid_Factory;
use Symfony\Component\Uid\Ulid;
final class Ulid_Generator extends Abstract_Id_Generator
{
    public function __construct(private readonly ?Ulid_Factory $factory = null)
    {
    }
    public function generate_id(Entity_Manager_Interface $em, $entity): Ulid
    {
        if ($this->factory) {
            return $this->factory->create();
        }
        return new Ulid();
    }
}