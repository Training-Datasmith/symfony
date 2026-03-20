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
use Php_Unit\Text_Ui\Configuration\Configuration as LegacyConfiguration;
use Php_Unit\Text_Ui\Configuration\Registry;
use Php_Unit\Text_Ui\Test_Runner as BaseRunner;
use Php_Unit\Text_Ui\Xml_Configuration\Configuration;
use Php_Unit\Text_Ui\Xml_Configuration\Loader;
use Symfony\Bridge\Php_Unit\Symfony_Tests_Listener;
/**
 * @internal
 */
class Command_For_V9 extends Base_Command
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
            if (!class_exists(Configuration::class) && !$configuration instanceof Legacy_Configuration) {
                $configuration = Registry::get_instance()->get($this->arguments['configuration']);
            } elseif (class_exists(Configuration::class) && !$configuration instanceof Configuration) {
                $configuration = (new Loader())->load($this->arguments['configuration']);
            }
            foreach ($configuration->listeners() as $registered_listener) {
                if (\Symfony\Bridge\Php_Unit\Symfony_Tests_Listener::class === ltrim((string) $registered_listener->class_name(), '\\')) {
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