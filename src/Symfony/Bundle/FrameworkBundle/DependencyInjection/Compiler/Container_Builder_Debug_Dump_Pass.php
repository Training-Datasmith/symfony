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

use Symfony\Component\Config\Config_Cache;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Resolve_Env_Placeholders_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Dumper\Xml_Dumper;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Filesystem\Filesystem;
/**
 * Dumps the ContainerBuilder to a cache file so that it can be used by
 * debugging tools such as the debug:container console command.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Container_Builder_Debug_Dump_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->get_parameter('debug.container.dump')) {
            return;
        }
        $file = $container->get_parameter('debug.container.dump');
        $cache = new Config_Cache($file, true);
        if ($cache->is_fresh()) {
            return;
        }
        $cache->write((new Xml_Dumper($container))->dump(), $container->get_resources());
        if (!str_ends_with($file, '.xml')) {
            return;
        }
        $file = substr_replace($file, '.ser', -4);
        try {
            $dump = new Container_Builder(clone $container->get_parameter_bag());
            $dump->set_definitions(unserialize(serialize($container->get_definitions())));
            $dump->set_aliases($container->get_aliases());
            if (($bag = $container->get_parameter_bag()) instanceof Env_Placeholder_Parameter_Bag) {
                (new Resolve_Env_Placeholders_Pass(null))->process($dump);
                $dump->__construct(new Env_Placeholder_Parameter_Bag($container->resolve_env_placeholders($this->escape_parameters($bag->all()))));
            }
            $fs = new Filesystem();
            $fs->dump_file($file, serialize($dump));
            $fs->chmod($file, 0666, umask());
        } catch (\Throwable $e) {
            $container->get_compiler()->log($this, $e->get_message());
            // ignore serialization and file-system errors
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    private function escape_parameters(array $parameters): array
    {
        $params = [];
        foreach ($parameters as $k => $v) {
            $params[$k] = match (true) {
                \is_array($v) => $this->escape_parameters($v),
                \is_string($v) => str_replace('%', '%%', $v),
                default => $v,
            };
        }
        return $params;
    }
}