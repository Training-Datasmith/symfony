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
namespace Symfony\Component\Dependency_Injection\Loader;

class Undefined_Extension_Handler
{
    private const BUNDLE_EXTENSIONS = ['debug' => 'DebugBundle', 'doctrine' => 'DoctrineBundle', 'doctrine_migrations' => 'DoctrineMigrationsBundle', 'framework' => 'FrameworkBundle', 'maker' => 'MakerBundle', 'monolog' => 'MonologBundle', 'security' => 'SecurityBundle', 'twig' => 'TwigBundle', 'twig_component' => 'TwigComponentBundle', 'ux_icons' => 'UXIconsBundle', 'web_profiler' => 'WebProfilerBundle'];
    public static function get_error_message(string $extension_name, ?string $loading_file_path, string $namespace_or_alias, array $found_extension_namespaces): string
    {
        $message = '';
        if (isset(self::BUNDLE_EXTENSIONS[$extension_name])) {
            $message .= \sprintf('Did you forget to install or enable the %s? ', self::BUNDLE_EXTENSIONS[$extension_name]);
        }
        $message .= match (true) {
            \is_string($loading_file_path) => \sprintf('There is no extension able to load the configuration for "%s" (in "%s"). ', $extension_name, $loading_file_path),
            default => \sprintf('There is no extension able to load the configuration for "%s". ', $extension_name),
        };
        return $message . \sprintf('Looked for namespace "%s", found "%s".', $namespace_or_alias, $found_extension_namespaces ? implode('", "', $found_extension_namespaces) : 'none');
    }
}