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
namespace Symfony\Bridge\Doctrine\Validator;

use Doctrine\Persistence\Manager_Registry;
use Symfony\Component\Validator\Object_Initializer_Interface;
/**
 * Automatically loads proxy object before validation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Doctrine_Initializer implements Object_Initializer_Interface
{
    public function __construct(protected Manager_Registry $registry)
    {
    }
    public function initialize(object $object): void
    {
        $this->registry->get_manager_for_class($object::class)?->initialize_object($object);
    }
}