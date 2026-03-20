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
namespace Symfony\Bridge\Doctrine\Form;

use Doctrine\Persistence\Manager_Registry;
use Symfony\Bridge\Doctrine\Form\Type\Entity_Type;
use Symfony\Component\Form\Abstract_Extension;
use Symfony\Component\Form\Form_Type_Guesser_Interface;
class Doctrine_Orm_Extension extends Abstract_Extension
{
    public function __construct(protected Manager_Registry $registry)
    {
    }
    protected function load_types(): array
    {
        return [new Entity_Type($this->registry)];
    }
    protected function load_type_guesser(): ?Form_Type_Guesser_Interface
    {
        return new Doctrine_Orm_Type_Guesser($this->registry);
    }
}