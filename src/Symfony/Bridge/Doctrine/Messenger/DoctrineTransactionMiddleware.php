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
use Symfony\Component\Messenger\Exception\Handler_Failed_Exception;
use Symfony\Component\Messenger\Middleware\Stack_Interface;
use Symfony\Component\Messenger\Stamp\Handled_Stamp;
/**
 * Wraps all handlers in a single doctrine transaction.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Doctrine_Transaction_Middleware extends Abstract_Doctrine_Middleware
{
    protected function handle_for_manager(Entity_Manager_Interface $entity_manager, Envelope $envelope, Stack_Interface $stack): Envelope
    {
        $entity_manager->get_connection()->begin_transaction();
        $success = false;
        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $entity_manager->flush();
            $entity_manager->get_connection()->commit();
            $success = true;
            return $envelope;
        } catch (\Throwable $exception) {
            if ($exception instanceof Handler_Failed_Exception) {
                // Remove all HandledStamp from the envelope so the retry will execute all handlers again.
                // When a handler fails, the queries of allegedly successful previous handlers just got rolled back.
                throw new Handler_Failed_Exception($exception->get_envelope()->without_all(Handled_Stamp::class), $exception->get_wrapped_exceptions());
            }
            throw $exception;
        } finally {
            $connection = $entity_manager->get_connection();
            if (!$success && $connection->is_transaction_active()) {
                $connection->roll_back();
            }
        }
    }
}