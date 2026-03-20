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

use Symfony\Component\Config\Config_Cache;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Compiler\Check_Alias_Validity_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Check_Type_Declarations_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Pass_Config;
use Symfony\Component\Dependency_Injection\Compiler\Resolve_Factory_Class_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Resolve_Parameter_Place_Holders_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Http_Kernel\Kernel;
#[As_Command(name: 'lint:container', description: 'Ensure that arguments injected into services match type declarations')]
final class Container_Lint_Command extends Command
{
    private Container_Builder $container;
    protected function configure(): void
    {
        $this->set_help('This command parses service definitions and ensures that injected values match the type declarations of each services\' class.')->add_option('resolve-env-vars', null, Input_Option::VALUE_NONE, 'Resolve environment variables and fail if one is missing.');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $error_io = $io->get_error_style();
        $resolve_env_vars = $input->get_option('resolve-env-vars');
        try {
            $container = $this->get_container_builder($resolve_env_vars);
        } catch (RuntimeException $e) {
            $error_io->error($e->get_message());
            return 2;
        }
        $container->set_parameter('container.build_time', time());
        try {
            $container->compile($resolve_env_vars);
        } catch (InvalidArgumentException $e) {
            $error_io->error($e->get_message());
            return 1;
        }
        $io->success('The container was linted successfully: all services are injected with values that are compatible with their type declarations.');
        return 0;
    }
    private function get_container_builder(bool $resolve_env_vars): Container_Builder
    {
        if (isset($this->container)) {
            return $this->container;
        }
        $kernel = $this->get_application()->get_kernel();
        $container = $kernel->get_container();
        $file = $kernel->is_debug() ? $container->get_parameter('debug.container.dump') : false;
        if (!$file || !(new Config_Cache($file, true))->is_fresh()) {
            if (!$kernel instanceof Kernel) {
                throw new RuntimeException(\sprintf('This command does not support the application kernel: "%s" does not extend "%s".', get_debug_type($kernel), Kernel::class));
            }
            $build_container = \Closure::bind(function (): Container_Builder {
                $this->initialize_bundles();
                return $this->build_container();
            }, $kernel, $kernel::class);
            $container = $build_container();
        } else {
            $container = unserialize(file_get_contents(substr_replace($file, '.ser', -4)));
            if (!$container instanceof Container_Builder) {
                throw new RuntimeException(\sprintf('This command does not support the application container: "%s" is not a "%s".', get_debug_type($container), Container_Builder::class));
            }
            if ($resolve_env_vars) {
                $container->get_compiler_pass_config()->set_optimization_passes([new Resolve_Parameter_Place_Holders_Pass(), new Resolve_Factory_Class_Pass()]);
            } else {
                $parameter_bag = $container->get_parameter_bag();
                $refl = new \ReflectionProperty($parameter_bag, 'resolved');
                $refl->set_value($parameter_bag, true);
                $container->get_compiler_pass_config()->set_optimization_passes([new Resolve_Factory_Class_Pass()]);
            }
            $container->get_compiler_pass_config()->set_before_optimization_passes([]);
            $container->get_compiler_pass_config()->set_before_removing_passes([]);
        }
        $container->set_parameter('container.build_hash', 'lint_container');
        $container->set_parameter('container.build_id', 'lint_container');
        $container->set_parameter('container.runtime_mode', 'web=0');
        $container->add_compiler_pass(new Check_Alias_Validity_Pass(), Pass_Config::TYPE_BEFORE_REMOVING, -100);
        $container->add_compiler_pass(new Check_Type_Declarations_Pass(true), Pass_Config::TYPE_AFTER_REMOVING, -100);
        return $this->container = $container;
    }
}