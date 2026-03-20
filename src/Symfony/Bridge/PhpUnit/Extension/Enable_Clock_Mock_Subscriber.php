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
use Php_Unit\Event\Test\Preparation_Started;
use Php_Unit\Event\Test\Preparation_Started_Subscriber;
use Php_Unit\Metadata\Group;
use Symfony\Bridge\Php_Unit\Attribute\Time_Sensitive;
use Symfony\Bridge\Php_Unit\Clock_Mock;
use Symfony\Bridge\Php_Unit\Metadata\Attribute_Reader;
/**
 * @internal
 */
class Enable_Clock_Mock_Subscriber implements Preparation_Started_Subscriber
{
    public function __construct(private readonly Attribute_Reader $reader)
    {
    }
    public function notify(Preparation_Started $event): void
    {
        $test = $event->test();
        if (!$test instanceof Test_Method) {
            return;
        }
        foreach ($test->metadata() as $metadata) {
            if ($metadata instanceof Group && 'time-sensitive' === $metadata->group_name()) {
                Clock_Mock::with_clock_mock(true);
                break;
            }
        }
        if ($this->reader->for_class_and_method($test->class_name(), $test->method_name(), Time_Sensitive::class)) {
            Clock_Mock::with_clock_mock(true);
        }
    }
}