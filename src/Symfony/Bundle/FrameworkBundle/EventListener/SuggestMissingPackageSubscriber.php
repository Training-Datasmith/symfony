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
namespace Symfony\Bundle\Framework_Bundle\Event_Listener;

use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Error_Event;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Suggests a package, that should be installed (via composer),
 * if the package is missing, and the input command namespace can be mapped to a Symfony bundle.
 *
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 *
 * @internal
 */
final class Suggest_Missing_Package_Subscriber implements Event_Subscriber_Interface
{
    private const PACKAGES = ['doctrine' => ['fixtures' => ['DoctrineFixturesBundle', 'doctrine/doctrine-fixtures-bundle --dev'], 'mongodb' => ['DoctrineMongoDBBundle', 'doctrine/mongodb-odm-bundle'], '_default' => ['Doctrine ORM', 'symfony/orm-pack']], 'make' => ['_default' => ['MakerBundle', 'symfony/maker-bundle --dev']], 'server' => ['_default' => ['Debug Bundle', 'symfony/debug-bundle --dev']]];
    public function on_console_error(Console_Error_Event $event): void
    {
        if (!$event->get_error() instanceof Command_Not_Found_Exception) {
            return;
        }
        [$namespace, $command] = explode(':', (string) $event->get_input()->get_first_argument()) + [1 => ''];
        if (!isset(self::PACKAGES[$namespace])) {
            return;
        }
        if (isset(self::PACKAGES[$namespace][$command])) {
            $suggestion = self::PACKAGES[$namespace][$command];
            $exact = true;
        } else {
            $suggestion = self::PACKAGES[$namespace]['_default'];
            $exact = false;
        }
        $error = $event->get_error();
        if ($error->get_alternatives() && !$exact) {
            return;
        }
        $message = \sprintf("%s\n\nYou may be looking for a command provided by the \"%s\" which is currently not installed. Try running \"composer require %s\".", $error->get_message(), $suggestion[0], $suggestion[1]);
        $event->set_error(new Command_Not_Found_Exception($message));
    }
    public static function get_subscribed_events(): array
    {
        return [Console_Events::ERROR => ['onConsoleError', 0]];
    }
}