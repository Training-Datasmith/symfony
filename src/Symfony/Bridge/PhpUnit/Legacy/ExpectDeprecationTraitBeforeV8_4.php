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

/**
 * @internal, use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait instead.
 */
trait Expect_Deprecation_Trait_Before_V8_4
{
    /**
     * @param string $message
     */
    protected function expect_deprecation($message): void
    {
        // Expected deprecations set by isolated tests need to be written to a file
        // so that the test running process can take account of them.
        if ($file = getenv('SYMFONY_EXPECTED_DEPRECATIONS_SERIALIZE')) {
            $this->get_test_result_object()->be_strict_about_tests_that_do_not_test_anything(false);
            $expected_deprecations = file_get_contents($file);
            if ($expected_deprecations) {
                $expected_deprecations = array_merge(unserialize($expected_deprecations), [$message]);
            } else {
                $expected_deprecations = [$message];
            }
            file_put_contents($file, serialize($expected_deprecations));
            return;
        }
        if (!Symfony_Tests_Listener_Trait::$previous_error_handler) {
            Symfony_Tests_Listener_Trait::$previous_error_handler = set_error_handler(Symfony_Tests_Listener_Trait::handle_error(...));
        }
        Symfony_Tests_Listener_Trait::$expected_deprecations[] = $message;
    }
}