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

use Symfony\Bridge\Php_Unit\Legacy\Expect_Deprecation_Trait_Before_V8_4;
use Symfony\Bridge\Php_Unit\Legacy\Expect_Deprecation_Trait_For_V8_4;
if (version_compare(\Php_Unit\Runner\Version::id(), '8.4.0', '<')) {
    trait Expect_Deprecation_Trait
    {
        use Expect_Deprecation_Trait_Before_V8_4;
    }
} else {
    /**
     * @method void expectDeprecation(string $message)
     */
    trait Expect_Deprecation_Trait
    {
        use Expect_Deprecation_Trait_For_V8_4;
    }
}