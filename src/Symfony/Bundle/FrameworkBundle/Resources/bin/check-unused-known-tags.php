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
if ('cli' !== \PHP_SAPI) {
    throw new Exception('This script must be run from the command line.');
}
require dirname(__DIR__, 6) . '/vendor/autoload.php';
use Symfony\Bundle\Framework_Bundle\Tests\Dependency_Injection\Compiler\Unused_Tags_Pass_Utils;
$target = dirname(__DIR__, 2) . '/DependencyInjection/Compiler/UnusedTagsPass.php';
$contents = file_get_contents($target);
$contents = preg_replace('{private const KNOWN_TAGS = \[(.+?)\];}sm', "private const KNOWN_TAGS = [\n        '" . implode("',\n        '", Unused_Tags_Pass_Utils::get_defined_tags()) . "',\n    ];", $contents);
file_put_contents($target, $contents);