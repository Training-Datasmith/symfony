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
namespace Symfony\Bridge\Php_Unit\Extension;

use Php_Unit\Event\Code\Test_Method;
use Php_Unit\Event\Test_Suite\Loaded;
use Php_Unit\Event\Test_Suite\Loaded_Subscriber;
use Php_Unit\Metadata\Group;
use Symfony\Bridge\Php_Unit\Attribute\Time_Sensitive;
use Symfony\Bridge\Php_Unit\Clock_Mock;
use Symfony\Bridge\Php_Unit\Metadata\Attribute_Reader;
/**
 * @internal
 */
class Register_Clock_Mock_Subscriber implements Loaded_Subscriber
{
    public function __construct(private readonly Attribute_Reader $reader)
    {
    }
    public function notify(Loaded $event): void
    {
        foreach ($event->test_suite()->tests() as $test) {
            if (!$test instanceof Test_Method) {
                continue;
            }
            foreach ($test->metadata() as $metadata) {
                if ($metadata instanceof Group && 'time-sensitive' === $metadata->group_name()) {
                    Clock_Mock::register($test->class_name());
                }
            }
            foreach ($this->reader->for_class_and_method($test->class_name(), $test->method_name(), Time_Sensitive::class) as $attribute) {
                Clock_Mock::register($attribute->class ?? $test->class_name());
            }
        }
    }
}