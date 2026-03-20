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
use Doctrine\Deprecations\Deprecation;
use Symfony\Bridge\Php_Unit\Deprecation_Error_Handler;
// Skip if we're using PHPUnit >=10
if (class_exists(Php_Unit\Metadata\Metadata::class)) {
    return;
}
// Detect if we need to serialize deprecations to a file.
if (in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && $file = getenv('SYMFONY_DEPRECATIONS_SERIALIZE')) {
    Deprecation_Error_Handler::collect_deprecations($file);
    return;
}
// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists(Php_Unit\Text_Ui\Command::class, false)) {
    return;
}
if (isset($file_identifier)) {
    unset($GLOBALS['__composer_autoload_files'][$file_identifier]);
}
if (class_exists(Deprecation::class)) {
    Deprecation::without_deduplication();
}
if ('disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    Deprecation_Error_Handler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
}