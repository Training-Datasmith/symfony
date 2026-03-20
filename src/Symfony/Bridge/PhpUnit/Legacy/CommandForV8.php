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
namespace Symfony\Bridge\Php_Unit\Legacy;

use Php_Unit\Text_Ui\Command as BaseCommand;
use Php_Unit\Text_Ui\Test_Runner as BaseRunner;
use Php_Unit\Util\Configuration;
use Symfony\Bridge\Php_Unit\Symfony_Tests_Listener;
/**
 * @internal
 */
class Command_For_V8 extends Base_Command
{
    protected function create_runner(): Base_Runner
    {
        $this->arguments['listeners'] ?? $this->arguments['listeners'] = [];
        $registered_locally = false;
        foreach ($this->arguments['listeners'] as $registered_listener) {
            if ($registered_listener instanceof Symfony_Tests_Listener) {
                $registered_listener->global_listener_disabled();
                $registered_locally = true;
                break;
            }
        }
        if (isset($this->arguments['configuration'])) {
            $configuration = $this->arguments['configuration'];
            if (!$configuration instanceof Configuration) {
                $configuration = Configuration::get_instance($this->arguments['configuration']);
            }
            foreach ($configuration->get_listener_configuration() as $registered_listener) {
                if (\Symfony\Bridge\Php_Unit\Symfony_Tests_Listener::class === ltrim((string) $registered_listener['class'], '\\')) {
                    $registered_locally = true;
                    break;
                }
            }
        }
        if (!$registered_locally) {
            $this->arguments['listeners'][] = new Symfony_Tests_Listener();
        }
        return parent::create_runner();
    }
}