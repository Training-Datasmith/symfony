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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\Stack_Interface;
use Symfony\Component\Messenger\Stamp\Consumed_By_Worker_Stamp;
/**
 * Closes connection and therefore saves number of connections.
 *
 * @author Fuong <insidestyles@gmail.com>
 */
class Doctrine_Close_Connection_Middleware extends Abstract_Doctrine_Middleware
{
    protected function handle_for_manager(Entity_Manager_Interface $entity_manager, Envelope $envelope, Stack_Interface $stack): Envelope
    {
        try {
            $connection = $entity_manager->get_connection();
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if (null !== $envelope->last(Consumed_By_Worker_Stamp::class)) {
                $connection->close();
            }
        }
    }
}