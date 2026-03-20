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
namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Manager_Registry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\Unrecoverable_Message_Handling_Exception;
use Symfony\Component\Messenger\Middleware\Middleware_Interface;
use Symfony\Component\Messenger\Middleware\Stack_Interface;
/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 *
 * @internal
 */
abstract class Abstract_Doctrine_Middleware implements Middleware_Interface
{
    public function __construct(protected Manager_Registry $manager_registry, protected ?string $entity_manager_name = null)
    {
    }
    final public function handle(Envelope $envelope, Stack_Interface $stack): Envelope
    {
        try {
            $entity_manager = $this->manager_registry->get_manager($this->entity_manager_name);
        } catch (\InvalidArgumentException $e) {
            throw new Unrecoverable_Message_Handling_Exception($e->get_message(), 0, $e);
        }
        return $this->handle_for_manager($entity_manager, $envelope, $stack);
    }
    abstract protected function handle_for_manager(Entity_Manager_Interface $entity_manager, Envelope $envelope, Stack_Interface $stack): Envelope;
}