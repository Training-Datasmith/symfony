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
class Translation_Update_Command_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('console.command.translation_extract')) {
            return;
        }
        $translation_writer_class = $container->get_parameter_bag()->resolve_value($container->find_definition('translation.writer')->get_class());
        if (!method_exists($translation_writer_class, 'getFormats')) {
            $container->remove_definition('console.command.translation_extract');
        }
    }
}