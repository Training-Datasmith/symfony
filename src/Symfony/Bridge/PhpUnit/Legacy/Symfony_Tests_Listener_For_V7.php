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

use Php_Unit\Framework\Test;
use Php_Unit\Framework\Test_Listener;
use Php_Unit\Framework\Test_Listener_Default_Implementation;
use Php_Unit\Framework\Test_Suite;
/**
 * Collects and replays skipped tests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Symfony_Tests_Listener_For_V7 implements Test_Listener
{
    use Test_Listener_Default_Implementation;
    private \Symfony\Bridge\Php_Unit\Legacy\Symfony_Tests_Listener_Trait $trait;
    public function __construct(array $mocked_namespaces = [])
    {
        $this->trait = new Symfony_Tests_Listener_Trait($mocked_namespaces);
    }
    public function global_listener_disabled(): void
    {
        $this->trait->global_listener_disabled();
    }
    public function start_test_suite(Test_Suite $suite): void
    {
        $this->trait->start_test_suite($suite);
    }
    public function add_skipped_test(Test $test, \Throwable $t, float $time): void
    {
        $this->trait->add_skipped_test($test, $t, $time);
    }
    public function start_test(Test $test): void
    {
        $this->trait->start_test($test);
    }
    public function end_test(Test $test, float $time): void
    {
        $this->trait->end_test($test, $time);
    }
}