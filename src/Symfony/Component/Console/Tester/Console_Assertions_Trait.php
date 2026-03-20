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
namespace Symfony\Component\Console\Tester;

use Symfony\Component\Console\Tester\Constraint\Command_Failed;
use Symfony\Component\Console\Tester\Constraint\Command_Is_Invalid;
use Symfony\Component\Console\Tester\Constraint\Command_Is_Successful;
/**
 * @psalm-require-extends \PHPUnit\Framework\TestCase
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
trait Console_Assertions_Trait
{
    public function assert_is_successful(Execution_Result $result, string $message = ''): void
    {
        $this->assert_that($result->status_code, new Command_Is_Successful(), $message);
    }
    public function assert_failed(Execution_Result $result, string $message = ''): void
    {
        $this->assert_that($result->status_code, new Command_Failed(), $message);
    }
    public function assert_is_invalid(Execution_Result $result, string $message = ''): void
    {
        $this->assert_that($result->status_code, new Command_Is_Invalid(), $message);
    }
    public function assert_result_equals(Execution_Result $result, ?int $expected_status_code = null, ?string $expected_output = null, ?string $expected_error_output = null, ?string $expected_display = null, string $message = ''): void
    {
        $expected = [];
        $actual = [];
        if (null !== $expected_status_code) {
            $expected['statusCode'] = $expected_status_code;
            $actual['statusCode'] = $result->status_code;
        }
        if (null !== $expected_output) {
            $expected['output'] = $expected_output;
            $actual['output'] = $result->get_output();
        }
        if (null !== $expected_error_output) {
            $expected['errorOutput'] = $expected_error_output;
            $actual['errorOutput'] = $result->get_error_output();
        }
        if (null !== $expected_display) {
            $expected['display'] = $expected_display;
            $actual['display'] = $result->get_display();
        }
        $this->assert_equals($expected, $actual, $message);
    }
}