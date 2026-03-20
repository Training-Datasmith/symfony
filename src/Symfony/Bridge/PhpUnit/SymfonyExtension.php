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
namespace Symfony\Bridge\Php_Unit;

use Doctrine\Deprecations\Deprecation;
use Php_Unit\Event\Code\Test;
use Php_Unit\Event\Code\Test_Method;
use Php_Unit\Event\Test\Before_Test_Method_Errored;
use Php_Unit\Event\Test\Before_Test_Method_Errored_Subscriber;
use Php_Unit\Event\Test\Errored;
use Php_Unit\Event\Test\Errored_Subscriber;
use Php_Unit\Event\Test\Finished;
use Php_Unit\Event\Test\Finished_Subscriber;
use Php_Unit\Event\Test\Skipped;
use Php_Unit\Event\Test\Skipped_Subscriber;
use Php_Unit\Metadata\Group;
use Php_Unit\Runner\Extension\Extension;
use Php_Unit\Runner\Extension\Facade;
use Php_Unit\Runner\Extension\Parameter_Collection;
use Php_Unit\Text_Ui\Configuration\Configuration;
use Symfony\Bridge\Php_Unit\Attribute\Dns_Sensitive;
use Symfony\Bridge\Php_Unit\Attribute\Time_Sensitive;
use Symfony\Bridge\Php_Unit\Extension\Enable_Clock_Mock_Subscriber;
use Symfony\Bridge\Php_Unit\Extension\Register_Clock_Mock_Subscriber;
use Symfony\Bridge\Php_Unit\Extension\Register_Dns_Mock_Subscriber;
use Symfony\Bridge\Php_Unit\Metadata\Attribute_Reader;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
class Symfony_Extension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, Parameter_Collection $parameters): void
    {
        $deprecations_namespaces_mapping = null;
        if ($parameters->has('deprecations-namespaces-mapping')) {
            $deprecations_namespaces_mapping = [];
            foreach (explode(',', $parameters->get('deprecations-namespaces-mapping')) as $pair) {
                [$key, $value] = explode('=>', $pair, 2);
                $deprecations_namespaces_mapping[trim($key)] = trim($value);
            }
        }
        if (class_exists(Debug_Class_Loader::class)) {
            Debug_Class_Loader::enable($deprecations_namespaces_mapping);
        }
        if (class_exists(Deprecation::class)) {
            Deprecation::without_deduplication();
        }
        $reader = new Attribute_Reader();
        if ($parameters->has('clock-mock-namespaces')) {
            foreach (explode(',', $parameters->get('clock-mock-namespaces')) as $namespace) {
                Clock_Mock::register($namespace . '\DummyClass');
            }
        }
        $facade->register_subscriber(new Register_Clock_Mock_Subscriber($reader));
        $facade->register_subscriber(new Enable_Clock_Mock_Subscriber($reader));
        $facade->register_subscriber(new class($reader) implements Errored_Subscriber
        {
            public function __construct(private readonly Attribute_Reader $reader)
            {
            }
            public function notify(Errored $event): void
            {
                Symfony_Extension::disable_clock_mock($event->test(), $this->reader);
                Symfony_Extension::disable_dns_mock($event->test(), $this->reader);
            }
        });
        $facade->register_subscriber(new class($reader) implements Finished_Subscriber
        {
            public function __construct(private readonly Attribute_Reader $reader)
            {
            }
            public function notify(Finished $event): void
            {
                Symfony_Extension::disable_clock_mock($event->test(), $this->reader);
                Symfony_Extension::disable_dns_mock($event->test(), $this->reader);
            }
        });
        $facade->register_subscriber(new class($reader) implements Skipped_Subscriber
        {
            public function __construct(private readonly Attribute_Reader $reader)
            {
            }
            public function notify(Skipped $event): void
            {
                Symfony_Extension::disable_clock_mock($event->test(), $this->reader);
                Symfony_Extension::disable_dns_mock($event->test(), $this->reader);
            }
        });
        if (interface_exists(Before_Test_Method_Errored_Subscriber::class)) {
            $facade->register_subscriber(new class($reader) implements Before_Test_Method_Errored_Subscriber
            {
                public function __construct(private readonly Attribute_Reader $reader)
                {
                }
                public function notify(Before_Test_Method_Errored $event): void
                {
                    if (method_exists($event, 'test')) {
                        Symfony_Extension::disable_clock_mock($event->test(), $this->reader);
                        Symfony_Extension::disable_dns_mock($event->test(), $this->reader);
                    } else {
                        Clock_Mock::with_clock_mock(false);
                        Dns_Mock::with_mocked_hosts([]);
                    }
                }
            });
        }
        if ($parameters->has('dns-mock-namespaces')) {
            foreach (explode(',', $parameters->get('dns-mock-namespaces')) as $namespace) {
                Dns_Mock::register($namespace . '\DummyClass');
            }
        }
        $facade->register_subscriber(new Register_Dns_Mock_Subscriber($reader));
    }
    /**
     * @internal
     */
    public static function disable_clock_mock(Test $test, Attribute_Reader $reader): void
    {
        if (self::has_group($test, 'time-sensitive', $reader, Time_Sensitive::class)) {
            Clock_Mock::with_clock_mock(false);
        }
    }
    /**
     * @internal
     */
    public static function disable_dns_mock(Test $test, Attribute_Reader $reader): void
    {
        if (self::has_group($test, 'dns-sensitive', $reader, Dns_Sensitive::class)) {
            Dns_Mock::with_mocked_hosts([]);
        }
    }
    /**
     * @internal
     */
    public static function has_group(Test $test, string $group_name, Attribute_Reader $reader, string $attribute): bool
    {
        if (!$test instanceof Test_Method) {
            return false;
        }
        foreach ($test->metadata() as $metadata) {
            if ($metadata instanceof Group && $group_name === $metadata->group_name()) {
                return true;
            }
        }
        return [] !== $reader->for_class_and_method($test->class_name(), $test->method_name(), $attribute);
    }
}