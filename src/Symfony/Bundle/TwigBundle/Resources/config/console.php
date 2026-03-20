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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Bridge\Twig\Command\Debug_Command;
use Symfony\Bundle\Twig_Bundle\Command\Lint_Command;
return static function (Container_Configurator $container): void {
    $container->services()->set('twig.command.debug', Debug_Command::class)->args([service('twig'), param('kernel.project_dir'), param('kernel.bundles_metadata'), param('twig.default_path'), service('debug.file_link_formatter')->null_on_invalid()])->tag('console.command')->set('twig.command.lint', Lint_Command::class)->args([service('twig'), abstract_arg('File name pattern')])->tag('console.command');
};