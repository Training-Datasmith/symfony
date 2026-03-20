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
namespace Symfony\Bridge\Doctrine\Dependency_Injection\Compiler_Pass;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Registers additional validators.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Doctrine_Validation_Pass implements Compiler_Pass_Interface
{
    public function __construct(private readonly string $manager_type)
    {
    }
    public function process(Container_Builder $container): void
    {
        $this->update_validator_mapping_files($container, 'xml', 'xml');
        $this->update_validator_mapping_files($container, 'yaml', 'yml');
    }
    /**
     * Gets the validation mapping files for the format and extends them with
     * files matching a doctrine search pattern (Resources/config/validation.orm.xml).
     */
    private function update_validator_mapping_files(Container_Builder $container, string $mapping, string $extension): void
    {
        if (!$container->has_parameter('validator.mapping.loader.' . $mapping . '_files_loader.mapping_files')) {
            return;
        }
        $files = $container->get_parameter('validator.mapping.loader.' . $mapping . '_files_loader.mapping_files');
        $validation_path = '/config/validation.' . $this->manager_type . '.' . $extension;
        foreach ($container->get_parameter('kernel.bundles_metadata') as $bundle) {
            if ($container->file_exists($file = $bundle['path'] . '/Resources' . $validation_path) || $container->file_exists($file = $bundle['path'] . $validation_path)) {
                $files[] = $file;
            }
        }
        $container->set_parameter('validator.mapping.loader.' . $mapping . '_files_loader.mapping_files', $files);
    }
}