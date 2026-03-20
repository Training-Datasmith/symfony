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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Compiler\Merge_Extension_Configuration_Pass as BaseMergeExtensionConfigurationPass;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Ensures certain extensions are always loaded.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Merge_Extension_Configuration_Pass extends Base_Merge_Extension_Configuration_Pass
{
    /**
     * @param string[] $extensions
     */
    public function __construct(private readonly array $extensions)
    {
    }
    public function process(Container_Builder $container): void
    {
        foreach ($this->extensions as $extension) {
            if (!\count($container->get_extension_config($extension))) {
                $container->load_from_extension($extension, []);
            }
        }
        parent::process($container);
    }
}