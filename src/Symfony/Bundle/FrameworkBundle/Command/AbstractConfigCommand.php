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
namespace Symfony\Bundle\Framework_Bundle\Command;

use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Style_Interface;
use Symfony\Component\Dependency_Injection\Extension\Configuration_Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
abstract class Abstract_Config_Command extends Container_Debug_Command
{
    protected function list_bundles(Output_Interface|Style_Interface $output): void
    {
        $title = 'Available registered bundles with their extension alias if available';
        $headers = ['Bundle name', 'Extension alias'];
        $rows = [];
        $bundles = $this->get_application()->get_kernel()->get_bundles();
        usort($bundles, static fn($bundle_a, $bundle_b): int => strcmp((string) $bundle_a->get_name(), (string) $bundle_b->get_name()));
        foreach ($bundles as $bundle) {
            $extension = $bundle->get_container_extension();
            $rows[] = [$bundle->get_name(), $extension ? $extension->get_alias() : ''];
        }
        if ($output instanceof Style_Interface) {
            $output->title($title);
            $output->table($headers, $rows);
        } else {
            $output->writeln($title);
            $table = new Table($output);
            $table->set_headers($headers)->set_rows($rows)->render();
        }
    }
    protected function list_non_bundle_extensions(Output_Interface|Style_Interface $output): void
    {
        $title = 'Available registered non-bundle extension aliases';
        $headers = ['Extension alias'];
        $rows = [];
        $kernel = $this->get_application()->get_kernel();
        $bundle_extensions = [];
        foreach ($kernel->get_bundles() as $bundle) {
            if ($extension = $bundle->get_container_extension()) {
                $bundle_extensions[$extension::class] = true;
            }
        }
        $extensions = $this->get_container_builder($kernel)->get_extensions();
        foreach ($extensions as $alias => $extension) {
            if (isset($bundle_extensions[$extension::class])) {
                continue;
            }
            $rows[] = [$alias];
        }
        if (!$rows) {
            return;
        }
        if ($output instanceof Style_Interface) {
            $output->title($title);
            $output->table($headers, $rows);
        } else {
            $output->writeln($title);
            $table = new Table($output);
            $table->set_headers($headers)->set_rows($rows)->render();
        }
    }
    protected function find_extension(string $name): Extension_Interface
    {
        $bundles = $this->initialize_bundles();
        $min_score = \INF;
        $kernel = $this->get_application()->get_kernel();
        if ($kernel instanceof Extension_Interface && ($kernel instanceof Configuration_Interface || $kernel instanceof Configuration_Extension_Interface)) {
            if ($name === $kernel->get_alias()) {
                return $kernel;
            }
            if ($kernel->get_alias()) {
                $distance = levenshtein($name, $kernel->get_alias());
                if ($distance < $min_score) {
                    $guess = $kernel->get_alias();
                    $min_score = $distance;
                }
            }
        }
        foreach ($bundles as $bundle) {
            if ($name === $bundle->get_name()) {
                if (!$bundle->get_container_extension()) {
                    throw new \LogicException(\sprintf('Bundle "%s" does not have a container extension.', $name));
                }
                return $bundle->get_container_extension();
            }
            $distance = levenshtein($name, $bundle->get_name());
            if ($distance < $min_score) {
                $guess = $bundle->get_name();
                $min_score = $distance;
            }
        }
        $container = $this->get_container_builder($kernel);
        if ($container->has_extension($name)) {
            return $container->get_extension($name);
        }
        foreach ($container->get_extensions() as $extension) {
            $distance = levenshtein($name, $extension->get_alias());
            if ($distance < $min_score) {
                $guess = $extension->get_alias();
                $min_score = $distance;
            }
        }
        if (!str_ends_with($name, 'Bundle')) {
            $message = \sprintf('No extensions with configuration available for "%s".', $name);
        } else {
            $message = \sprintf('No extension with alias "%s" is enabled.', $name);
        }
        if (isset($guess) && $min_score < 3) {
            $message .= \sprintf("\n\nDid you mean \"%s\"?", $guess);
        }
        throw new LogicException($message);
    }
    public function validate_configuration(Extension_Interface $extension, mixed $configuration): void
    {
        if (!$configuration) {
            throw new \LogicException(\sprintf('The extension with alias "%s" does not have its getConfiguration() method setup.', $extension->get_alias()));
        }
        if (!$configuration instanceof Configuration_Interface) {
            throw new \LogicException(\sprintf('Configuration class "%s" should implement ConfigurationInterface in order to be dumpable.', get_debug_type($configuration)));
        }
    }
    private function initialize_bundles(): array
    {
        // Re-build bundle manually to initialize DI extensions that can be extended by other bundles in their build() method
        // as this method is not called when the container is loaded from the cache.
        $kernel = $this->get_application()->get_kernel();
        $container = $this->get_container_builder($kernel);
        $bundles = $kernel->get_bundles();
        foreach ($bundles as $bundle) {
            if ($extension = $bundle->get_container_extension()) {
                $container->register_extension($extension);
            }
        }
        foreach ($bundles as $bundle) {
            $bundle->build($container);
        }
        return $bundles;
    }
}