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
namespace Symfony\Component\Config\Definition;

/**
 * This class is the entry point for config normalization/merging/finalization.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final
 */
class Processor
{
    /**
     * Processes an array of configurations.
     *
     * @param array $configs An array of configuration items to process
     */
    public function process(Node_Interface $config_tree, array $configs): array
    {
        $current_config = [];
        foreach ($configs as $config) {
            $config = $config_tree->normalize($config);
            $current_config = $config_tree->merge($current_config, $config);
        }
        return $config_tree->finalize($current_config);
    }
    /**
     * Processes an array of configurations.
     *
     * @param array $configs An array of configuration items to process
     */
    public function process_configuration(Configuration_Interface $configuration, array $configs): array
    {
        return $this->process($configuration->get_config_tree_builder()->build_tree(), $configs);
    }
    /**
     * Normalizes a configuration entry.
     *
     * This method returns a normalize configuration array for a given key
     * to remove the differences due to the original format (YAML and XML mainly).
     *
     * Here is an example.
     *
     * The configuration in XML:
     *
     * <twig:extension>twig.extension.foo</twig:extension>
     * <twig:extension>twig.extension.bar</twig:extension>
     *
     * And the same configuration in YAML:
     *
     * extensions: ['twig.extension.foo', 'twig.extension.bar']
     *
     * @param array       $config A config array
     * @param string      $key    The key to normalize
     * @param string|null $plural The plural form of the key if it is irregular
     */
    public static function normalize_config(array $config, string $key, ?string $plural = null): array
    {
        $plural ??= $key . 's';
        if (isset($config[$plural])) {
            return $config[$plural];
        }
        if (isset($config[$key])) {
            if (\is_string($config[$key]) || !\is_int(key($config[$key]))) {
                // only one
                return [$config[$key]];
            }
            return $config[$key];
        }
        return [];
    }
}