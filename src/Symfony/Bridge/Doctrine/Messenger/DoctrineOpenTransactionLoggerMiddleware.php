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
use Psr\Log\Logger_Interface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\Stack_Interface;
/**
 * Middleware to log when transaction has been left open.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Doctrine_Open_Transaction_Logger_Middleware extends Abstract_Doctrine_Middleware
{
    private bool $is_handling = false;
    public function __construct(Manager_Registry $manager_registry, ?string $entity_manager_name = null, private readonly ?Logger_Interface $logger = null)
    {
        parent::__construct($manager_registry, $entity_manager_name);
    }
    protected function handle_for_manager(Entity_Manager_Interface $entity_manager, Envelope $envelope, Stack_Interface $stack): Envelope
    {
        if ($this->is_handling) {
            return $stack->next()->handle($envelope, $stack);
        }
        $this->is_handling = true;
        $initial_transaction_level = $entity_manager->get_connection()->get_transaction_nesting_level();
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if ($entity_manager->get_connection()->get_transaction_nesting_level() > $initial_transaction_level) {
                $this->logger?->error('A handler opened a transaction but did not close it.', ['message' => $envelope->get_message()]);
            }
            $this->is_handling = false;
        }
    }
}