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
namespace Symfony\Bridge\Php_Unit\Text_Ui;

if (version_compare(\Php_Unit\Runner\Version::id(), '9.0.0', '<')) {
    class_alias(\Symfony\Bridge\Php_Unit\Legacy\Command_For_V8::class, \Symfony\Bridge\Php_Unit\Text_Ui\Command::class);
} else {
    class_alias(\Symfony\Bridge\Php_Unit\Legacy\Command_For_V9::class, \Symfony\Bridge\Php_Unit\Text_Ui\Command::class);
}
if (false) {
    class Command
    {
    }
}