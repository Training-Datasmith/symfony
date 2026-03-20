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

use Php_Unit\Runner\Version;
if (version_compare(Version::id(), '11.0.0', '<')) {
    trait Expect_User_Deprecation_Message_Trait
    {
        use Expect_Deprecation_Trait;
        final protected function expect_user_deprecation_message(string $expected_user_deprecation_message): void
        {
            $this->expect_deprecation(str_replace('%', '%%', $expected_user_deprecation_message));
        }
    }
} else {
    trait Expect_User_Deprecation_Message_Trait
    {
    }
}