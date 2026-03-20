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
namespace Symfony\Bridge\Doctrine\Schema_Listener;

use Doctrine\ORM\Tools\Event\Generate_Schema_Event_Args;
use Symfony\Bridge\Doctrine\Security\Remember_Me\Doctrine_Token_Provider;
use Symfony\Component\Security\Http\Remember_Me\Persistent_Remember_Me_Handler;
use Symfony\Component\Security\Http\Remember_Me\Remember_Me_Handler_Interface;
/**
 * Automatically adds the rememberme table needed for the {@see DoctrineTokenProvider}.
 */
class Remember_Me_Token_Provider_Doctrine_Schema_Listener extends Abstract_Schema_Listener
{
    /**
     * @param iterable<mixed, RememberMeHandlerInterface> $rememberMeHandlers
     */
    public function __construct(private readonly iterable $remember_me_handlers)
    {
    }
    public function post_generate_schema(Generate_Schema_Event_Args $event): void
    {
        $connection = $event->get_entity_manager()->get_connection();
        $schema = $event->get_schema();
        foreach ($this->remember_me_handlers as $remember_me_handler) {
            if ($remember_me_handler instanceof Persistent_Remember_Me_Handler && ($token_provider = $remember_me_handler->get_token_provider()) instanceof Doctrine_Token_Provider) {
                $is_same_database_checker = $this->get_is_same_database_checker($connection);
                $this->filter_schema_changes($schema, $connection, static function () use ($token_provider, $schema, $connection, $is_same_database_checker): void {
                    /* @var DoctrineTokenProvider $tokenProvider */
                    $token_provider->configure_schema($schema, $connection, $is_same_database_checker);
                });
            }
        }
    }
}