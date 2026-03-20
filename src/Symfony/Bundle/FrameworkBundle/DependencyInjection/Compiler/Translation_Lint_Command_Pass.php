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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Translation\Translator_Bag_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
final class Translation_Lint_Command_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('console.command.translation_lint') || !$container->has('translator')) {
            return;
        }
        $translator_class = $container->get_parameter_bag()->resolve_value($container->find_definition('translator')->get_class());
        if (!is_subclass_of($translator_class, Translator_Interface::class) || !is_subclass_of($translator_class, Translator_Bag_Interface::class)) {
            $container->remove_definition('console.command.translation_lint');
        }
    }
}