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
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Pdo_Session_Handler;
final class Pdo_Session_Handler_Schema_Listener extends Abstract_Schema_Listener
{
    private Pdo_Session_Handler $session_handler;
    public function __construct(\Session_Handler_Interface $session_handler)
    {
        if ($session_handler instanceof Pdo_Session_Handler) {
            $this->session_handler = $session_handler;
        }
    }
    public function post_generate_schema(Generate_Schema_Event_Args $event): void
    {
        if (!isset($this->session_handler)) {
            return;
        }
        $connection = $event->get_entity_manager()->get_connection();
        $schema = $event->get_schema();
        $is_same_database_checker = $this->get_is_same_database_checker($connection);
        $session_handler = $this->session_handler;
        $this->filter_schema_changes($schema, $connection, static function () use ($session_handler, $schema, $is_same_database_checker): void {
            $session_handler->configure_schema($schema, $is_same_database_checker);
        });
    }
}