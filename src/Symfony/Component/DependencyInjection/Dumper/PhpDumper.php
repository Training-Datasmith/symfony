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
namespace Symfony\Component\Dependency_Injection\Dumper;

use Composer\Autoload\Class_Loader;
use Symfony\Component\Config\Resource\File_Resource;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Lazy_Closure;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Analyze_Service_References_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Check_Circular_References_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Service_Reference_Graph_Node;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Env_Parameter_Exception;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Expression_Language;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper\Dumper_Interface;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper\Lazy_Service_Dumper;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper\Null_Dumper;
use Symfony\Component\Dependency_Injection\Loader\File_Loader;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Service_Locator as BaseServiceLocator;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Component\Dependency_Injection\Variable;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
use Symfony\Component\Expression_Language\Expression;
/**
 * PhpDumper dumps a service container as a PHP class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Php_Dumper extends Dumper
{
    /**
     * Characters that might appear in the generated variable name as first character.
     */
    public const FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz';
    /**
     * Characters that might appear in the generated variable name as any but the first character.
     */
    public const NON_FIRST_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_';
    /** @var \SplObjectStorage<Definition, Variable>|null */
    private ?\Spl_Object_Storage $definition_variables = null;
    private ?array $reference_variables = null;
    private int $variable_count;
    private ?\Spl_Object_Storage $inlined_definitions = null;
    private ?array $service_calls = null;
    private array $reserved_variables = ['instance', 'class', 'this', 'container'];
    private Expression_Language $expression_language;
    private ?string $target_dir_regex = null;
    private int $target_dir_max_matches;
    private string $doc_star;
    private array $service_id_to_method_name_map;
    private array $used_method_names;
    private string $namespace;
    private bool $as_files;
    private string $hot_path_tag;
    private array $preload_tags;
    private bool $inline_factories;
    private bool $inline_requires;
    private array $inlined_requires = [];
    private array $circular_references = [];
    private array $single_use_private_ids = [];
    private array $preload = [];
    private bool $add_get_service = false;
    private array $located_ids = [];
    private string $service_locator_tag;
    private array $exported_variables = [];
    private array $dynamic_parameters = [];
    private string $base_class;
    private string $class;
    private Dumper_Interface $proxy_dumper;
    private bool $has_proxy_dumper = true;
    public function __construct(Container_Builder $container)
    {
        if (!$container->is_compiled()) {
            throw new LogicException('Cannot dump an uncompiled container.');
        }
        parent::__construct($container);
    }
    /**
     * Sets the dumper to be used when dumping proxies in the generated container.
     */
    public function set_proxy_dumper(Dumper_Interface $proxy_dumper): void
    {
        $this->proxy_dumper = $proxy_dumper;
        $this->has_proxy_dumper = !$proxy_dumper instanceof Null_Dumper;
    }
    /**
     * Dumps the service container as a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *  * namespace:  The class namespace
     *  * as_files:   To split the container in several files
     *
     * @return string|array A PHP class representing the service container or an array of PHP files if the "as_files" option is set
     *
     * @throws EnvParameterException When an env var exists but has not been dumped
     */
    public function dump(array $options = []): string|array
    {
        $this->located_ids = [];
        $this->target_dir_regex = null;
        $this->inlined_requires = [];
        $this->exported_variables = [];
        $this->dynamic_parameters = [];
        $options = array_merge(['class' => 'ProjectServiceContainer', 'base_class' => 'Container', 'namespace' => '', 'as_files' => false, 'debug' => true, 'hot_path_tag' => 'container.hot_path', 'preload_tags' => ['container.preload', 'container.no_preload'], 'inline_factories' => null, 'inline_class_loader' => null, 'preload_classes' => [], 'service_locator_tag' => 'container.service_locator', 'build_time' => filter_var($_SERVER['SOURCE_DATE_EPOCH'] ?? null, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE) ?? time()], $options);
        $this->add_get_service = false;
        $this->namespace = $options['namespace'];
        $this->as_files = $options['as_files'];
        $this->hot_path_tag = $options['hot_path_tag'];
        $this->preload_tags = $options['preload_tags'];
        $this->inline_factories = false;
        if (isset($options['inline_factories'])) {
            $this->inline_factories = $this->as_files && $options['inline_factories'];
        }
        $this->inline_requires = $options['debug'];
        if (isset($options['inline_class_loader'])) {
            $this->inline_requires = $options['inline_class_loader'];
        }
        $this->service_locator_tag = $options['service_locator_tag'];
        $this->class = $options['class'];
        if (!str_starts_with((string) $base_class = $options['base_class'], '\\') && 'Container' !== $base_class) {
            $base_class = \sprintf('%s\%s', $options['namespace'] ? '\\' . $options['namespace'] : '', $base_class);
            $this->base_class = $base_class;
        } elseif ('Container' === $base_class) {
            $this->base_class = Container::class;
        } else {
            $this->base_class = $base_class;
        }
        $this->initialize_method_names_map('Container' === $base_class ? Container::class : $base_class);
        if (!$this->has_proxy_dumper) {
            (new Analyze_Service_References_Pass(true, false))->process($this->container);
            (new Check_Circular_References_Pass())->process($this->container);
        }
        $this->analyze_references();
        $this->doc_star = $options['debug'] ? '*' : '';
        if (!empty($options['file']) && is_dir($dir = \dirname((string) $options['file']))) {
            // Build a regexp where the first root dirs are mandatory,
            // but every other sub-dir is optional up to the full path in $dir
            // Mandate at least 1 root dir and not more than 5 optional dirs.
            $dir = explode(\DIRECTORY_SEPARATOR, realpath($dir));
            $i = \count($dir);
            if (2 + (int) ('\\' === \DIRECTORY_SEPARATOR) <= $i) {
                $regex = '';
                $last_optional_dir = $i > 8 ? $i - 5 : 2 + (int) ('\\' === \DIRECTORY_SEPARATOR);
                $this->target_dir_max_matches = $i - $last_optional_dir;
                while (--$i >= $last_optional_dir) {
                    $regex = \sprintf('(%s%s)?', preg_quote(\DIRECTORY_SEPARATOR . $dir[$i], '#'), $regex);
                }
                do {
                    $regex = preg_quote(\DIRECTORY_SEPARATOR . $dir[$i], '#') . $regex;
                } while (0 < --$i);
                $this->target_dir_regex = '#(^|file://|[:;, \|\r\n])' . preg_quote($dir[0], '#') . $regex . '#';
            }
        }
        $proxy_classes = $this->inline_factories ? $this->generate_proxy_classes() : null;
        if ($options['preload_classes']) {
            $this->preload = array_combine($options['preload_classes'], $options['preload_classes']);
        }
        $code = $this->add_default_parameters_method();
        $code = $this->start_class($options['class'], $base_class, $this->inline_factories && $proxy_classes) . $this->add_services($services) . $this->add_deprecated_aliases() . $code;
        $proxy_classes ??= $this->generate_proxy_classes();
        if ($this->add_get_service) {
            $code = preg_replace("/\r?\n\r?\n    public function __construct.+?\\{\r?\n/s", "\n    protected \\Closure \$getService;\$0", $code, 1);
        }
        if ($this->as_files) {
            $file_template = <<<EOF
            <?php
            
            use Symfony\\Component\\DependencyInjection\\Argument\\RewindableGenerator;
            use Symfony\\Component\\DependencyInjection\\ContainerInterface;
            use Symfony\\Component\\DependencyInjection\\Exception\\RuntimeException;
            
            /*{$this->doc_star}
             * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
             */
            class %s extends {$options['class']}
            {%s}
            
            EOF;
            $files = [];
            $preloaded_files = [];
            $ids = $this->container->get_removed_ids();
            foreach ($this->container->get_definitions() as $id => $definition) {
                if (!$definition->is_public() && '.' !== ($id[0] ?? '-')) {
                    $ids[$id] = true;
                }
            }
            if ($ids = array_keys($ids)) {
                sort($ids);
                $c = "<?php\n\nreturn [\n";
                foreach ($ids as $id) {
                    $c .= '    ' . $this->do_export($id) . " => true,\n";
                }
                $files['removed-ids.php'] = $c . "];\n";
            }
            if (!$this->inline_factories) {
                foreach ($this->generate_service_files($services) as $file => [$c, $preload]) {
                    $files[$file] = \sprintf($file_template, substr((string) $file, 0, -4), $c);
                    if ($preload) {
                        $preloaded_files[$file] = $file;
                    }
                }
                foreach ($proxy_classes as $file => $c) {
                    $files[$file] = "<?php\n" . $c;
                    $preloaded_files[$file] = $file;
                }
            }
            $code .= $this->end_class();
            if ($this->inline_factories && $proxy_classes) {
                $files['proxy-classes.php'] = "<?php\n\n";
                foreach ($proxy_classes as $c) {
                    $files['proxy-classes.php'] .= $c;
                }
            }
            $files[$options['class'] . '.php'] = $code;
            $hash = ucfirst(strtr(Container_Builder::hash($files), '._', 'xx'));
            $code = [];
            foreach ($files as $file => $c) {
                $code["Container{$hash}/{$file}"] = substr_replace($c, "<?php\n\nnamespace Container{$hash};\n", 0, 6);
                if (isset($preloaded_files[$file])) {
                    $preloaded_files[$file] = "Container{$hash}/{$file}";
                }
            }
            $namespace_line = $this->namespace ? "\nnamespace {$this->namespace};\n" : '';
            $time = $options['build_time'];
            $id = hash('crc32', $hash . $time);
            $this->as_files = false;
            if ($this->preload && null !== $autoload_file = $this->get_autoload_file()) {
                $autoload_file = trim((string) $this->export($autoload_file), '()\\');
                $preloaded_files = array_reverse($preloaded_files);
                if ('' !== $preloaded_files = implode("';\nrequire __DIR__.'/", $preloaded_files)) {
                    $preloaded_files = "require __DIR__.'/{$preloaded_files}';\n";
                }
                $code[$options['class'] . '.preload.php'] = <<<EOF
                <?php
                
                // This file has been auto-generated by the Symfony Dependency Injection Component
                // You can reference it in the "opcache.preload" php.ini setting on PHP >= 7.4 when preloading is desired
                
                use Symfony\\Component\\DependencyInjection\\Dumper\\Preloader;
                
                if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
                    return;
                }
                
                require {$autoload_file};
                (require __DIR__.'/{$options['class']}.php')->set(\\Container{$hash}\\{$options['class']}::class, null);
                {$preloaded_files}
                \$classes = [];
                
                EOF;
                foreach ($this->preload as $class) {
                    if (!$class) {
                        continue;
                    }
                    if (str_contains($class, '$')) {
                        continue;
                    }
                    if (\in_array($class, ['int', 'float', 'string', 'bool', 'resource', 'object', 'array', 'null', 'callable', 'iterable', 'mixed', 'void', 'never'], true)) {
                        continue;
                    }
                    if (!(class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) || (new \ReflectionClass($class))->is_user_defined()) {
                        $code[$options['class'] . '.preload.php'] .= \sprintf("\$classes[] = '%s';\n", $class);
                    }
                }
                $code[$options['class'] . '.preload.php'] .= <<<'EOF'
                
                $preloaded = Preloader::preload($classes);
                
                EOF;
            }
            $code[$options['class'] . '.php'] = <<<EOF
            <?php
            {$namespace_line}
            // This file has been auto-generated by the Symfony Dependency Injection Component for internal use.
            
            if (\\class_exists(\\Container{$hash}\\{$options['class']}::class, false)) {
                // no-op
            } elseif (!include __DIR__.'/Container{$hash}/{$options['class']}.php') {
                touch(__DIR__.'/Container{$hash}.legacy');
            
                return;
            }
            
            if (!\\class_exists({$options['class']}::class, false)) {
                \\class_alias(\\Container{$hash}\\{$options['class']}::class, {$options['class']}::class, false);
            }
            
            return new \\Container{$hash}\\{$options['class']}([
                'container.build_hash' => '{$hash}',
                'container.build_id' => '{$id}',
                'container.build_time' => {$time},
                'container.runtime_mode' => \\in_array(\\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) ? 'web=0' : 'web=1',
            ], __DIR__.\\DIRECTORY_SEPARATOR.'Container{$hash}');
            
            EOF;
        } else {
            $code .= $this->end_class();
            foreach ($proxy_classes as $c) {
                $code .= $c;
            }
        }
        $this->target_dir_regex = null;
        $this->inlined_requires = [];
        $this->circular_references = [];
        $this->located_ids = [];
        $this->exported_variables = [];
        $this->dynamic_parameters = [];
        $this->preload = [];
        $unused_envs = [];
        foreach ($this->container->get_env_counters() as $env => $use) {
            if (!$use) {
                $unused_envs[] = $env;
            }
        }
        if ($unused_envs) {
            throw new Env_Parameter_Exception($unused_envs, null, 'Environment variables "%s" are never used. Please, check your container\'s configuration.');
        }
        return $code;
    }
    /**
     * Retrieves the currently set proxy dumper or instantiates one.
     */
    private function get_proxy_dumper(): Dumper_Interface
    {
        return $this->proxy_dumper ??= new Lazy_Service_Dumper($this->class);
    }
    private function analyze_references(): void
    {
        (new Analyze_Service_References_Pass(false, $this->has_proxy_dumper))->process($this->container);
        $checked_nodes = [];
        $this->circular_references = [];
        $this->single_use_private_ids = [];
        foreach ($this->container->get_compiler()->get_service_reference_graph()->get_nodes() as $id => $node) {
            if (!$node->get_value() instanceof Definition) {
                continue;
            }
            if ($this->is_single_use_private_node($node)) {
                $this->single_use_private_ids[$id] = $id;
            }
            $this->collect_circular_references($id, $node->get_out_edges(), $checked_nodes);
        }
        $this->container->get_compiler()->get_service_reference_graph()->clear();
        $this->single_use_private_ids = array_diff_key($this->single_use_private_ids, $this->circular_references);
    }
    private function collect_circular_references(string $source_id, array $edges, array &$checked_nodes, array &$loops = [], array $path = [], bool $by_constructor = true): void
    {
        $path[$source_id] = $by_constructor;
        $checked_nodes[$source_id] = true;
        foreach ($edges as $edge) {
            $node = $edge->get_dest_node();
            $id = $node->get_id();
            if ($source_id === $id && !$edge->is_lazy()) {
                continue;
            }
            if (!$node->get_value() instanceof Definition) {
                continue;
            }
            if ($edge->is_weak()) {
                continue;
            }
            if (isset($path[$id])) {
                $loop = null;
                $loop_by_constructor = $edge->is_referenced_by_constructor() && !$edge->is_lazy();
                $path_in_loop = [$id, []];
                foreach ($path as $k => $path_by_constructor) {
                    if (null !== $loop) {
                        $loop[] = $k;
                        $path_in_loop[1][$k] = $path_by_constructor;
                        $loops[$k][] =& $path_in_loop;
                        $loop_by_constructor = $loop_by_constructor && $path_by_constructor;
                    } elseif ($k === $id) {
                        $loop = [];
                    }
                }
                $this->add_circular_references($id, $loop, $loop_by_constructor);
            } elseif (!isset($checked_nodes[$id])) {
                $this->collect_circular_references($id, $node->get_out_edges(), $checked_nodes, $loops, $path, $edge->is_referenced_by_constructor() && !$edge->is_lazy());
            } elseif (isset($loops[$id])) {
                // we already had detected loops for this edge
                // let's check if we have a common ancestor in one of the detected loops
                foreach ($loops[$id] as [$first, $loop_path]) {
                    if (!isset($path[$first])) {
                        continue;
                    }
                    // We have a common ancestor, let's fill the current path
                    $fill_path = null;
                    foreach ($loop_path as $k => $path_by_constructor) {
                        if (null !== $fill_path) {
                            $fill_path[$k] = $path_by_constructor;
                        } elseif ($k === $id) {
                            $fill_path = $path;
                            $fill_path[$k] = $path_by_constructor;
                        }
                    }
                    // we can now build the loop
                    $loop = null;
                    $loop_by_constructor = $edge->is_referenced_by_constructor() && !$edge->is_lazy();
                    foreach ($fill_path as $k => $path_by_constructor) {
                        if (null !== $loop) {
                            $loop[] = $k;
                            $loop_by_constructor = $loop_by_constructor && $path_by_constructor;
                        } elseif ($k === $first) {
                            $loop = [];
                        }
                    }
                    $this->add_circular_references($first, $loop, $loop_by_constructor);
                    break;
                }
            }
        }
        unset($path[$source_id]);
    }
    private function add_circular_references(string $source_id, array $current_path, bool $by_constructor): void
    {
        $current_id = $source_id;
        $current_path = array_reverse($current_path);
        $current_path[] = $current_id;
        foreach ($current_path as $parent_id) {
            if (empty($this->circular_references[$parent_id][$current_id])) {
                $this->circular_references[$parent_id][$current_id] = $by_constructor;
            }
            $current_id = $parent_id;
        }
    }
    private function collect_lineage(string $class, array &$lineage): void
    {
        if (isset($lineage[$class])) {
            return;
        }
        if (!$r = $this->container->get_reflection_class($class, false)) {
            return;
        }
        if (is_a($class, $this->base_class, true)) {
            return;
        }
        $file = $r->get_file_name();
        if ($file && str_ends_with($file, ') : eval()\'d code')) {
            $file = substr($file, 0, strrpos($file, '(', -17));
        }
        if (!$file || $this->do_export($file) === $exported_file = $this->export($file)) {
            return;
        }
        $lineage[$class] = substr((string) $exported_file, 1, -1);
        if ($parent = $r->get_parent_class()) {
            $this->collect_lineage($parent->name, $lineage);
        }
        foreach ($r->get_interfaces() as $parent) {
            $this->collect_lineage($parent->name, $lineage);
        }
        foreach ($r->get_traits() as $parent) {
            $this->collect_lineage($parent->name, $lineage);
        }
        unset($lineage[$class]);
        $lineage[$class] = substr((string) $exported_file, 1, -1);
    }
    private function generate_proxy_classes(): array
    {
        $proxy_classes = [];
        $already_generated = [];
        $definitions = $this->container->get_definitions();
        $strip = '' === $this->doc_star;
        $proxy_dumper = $this->get_proxy_dumper();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition = $this->is_proxy_candidate($definition, $as_ghost_object, $id)) {
                continue;
            }
            if (isset($already_generated[$as_ghost_object][$class = $definition->get_class()])) {
                continue;
            }
            $already_generated[$as_ghost_object][$class] = true;
            foreach (array_column($definition->get_tag('proxy'), 'interface') ?: [$class] as $r) {
                if (!$r = $this->container->get_reflection_class($r)) {
                    continue;
                }
                do {
                    if ($file = $r->get_file_name()) {
                        if (str_ends_with($file, ') : eval()\'d code')) {
                            $file = substr($file, 0, strrpos($file, '(', -17));
                        }
                        if (is_file($file)) {
                            $this->container->add_resource(new File_Resource($file));
                        }
                    }
                    $r = $r->get_parent_class() ?: null;
                } while ($r?->is_user_defined());
            }
            if ("\n" === $proxy_code = "\n" . $proxy_dumper->get_proxy_code($definition, $id)) {
                continue;
            }
            if ($this->inline_requires) {
                $lineage = [];
                $this->collect_lineage($class, $lineage);
                $code = '';
                foreach (array_diff_key(array_flip($lineage), $this->inlined_requires) as $file => $class) {
                    if ($this->inline_factories) {
                        $this->inlined_requires[$file] = true;
                    }
                    $code .= \sprintf("include_once %s;\n", $file);
                }
                $proxy_code = $code . $proxy_code;
            }
            if ($strip) {
                $proxy_code = "<?php\n" . $proxy_code;
                $proxy_code = substr(self::strip_comments($proxy_code), 5);
            }
            $proxy_class = $this->inline_requires ? substr($proxy_code, \strlen($code)) : $proxy_code;
            $i = strpos($proxy_class, 'class');
            $proxy_class = substr($proxy_class, 6 + $i, strpos($proxy_class, ' ', 7 + $i) - $i - 6);
            if ($this->as_files || $this->namespace) {
                $proxy_code .= "\nif (!\\class_exists('{$proxy_class}', false)) {\n    \\class_alias(__NAMESPACE__.'\\\\{$proxy_class}', '{$proxy_class}', false);\n}\n";
            }
            $proxy_classes[$proxy_class . '.php'] = $proxy_code;
        }
        return $proxy_classes;
    }
    private function add_service_include(string $c_id, Definition $definition, bool $is_proxy_candidate): string
    {
        $code = '';
        if ($this->inline_requires && (!$this->is_hot_path($definition) || $is_proxy_candidate)) {
            $lineage = [];
            foreach ($this->inlined_definitions as $def) {
                if (!$def->is_deprecated()) {
                    foreach ($this->get_classes($def, $c_id) as $class) {
                        $this->collect_lineage($class, $lineage);
                    }
                }
            }
            foreach ($this->service_calls as $id => [$call_count, $behavior]) {
                if ('service_container' !== $id && $id !== $c_id && Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE !== $behavior && $this->container->has($id) && $this->is_trivial_instance($def = $this->container->find_definition($id))) {
                    foreach ($this->get_classes($def, $c_id) as $class) {
                        $this->collect_lineage($class, $lineage);
                    }
                }
            }
            foreach (array_diff_key(array_flip($lineage), $this->inlined_requires) as $file => $class) {
                $code .= \sprintf("        include_once %s;\n", $file);
            }
        }
        foreach ($this->inlined_definitions as $def) {
            if ($file = $def->get_file()) {
                $file = $this->dump_value($file);
                $file = '(' === $file[0] ? substr($file, 1, -1) : $file;
                $code .= \sprintf("        include_once %s;\n", $file);
            }
        }
        if ('' !== $code) {
            $code .= "\n";
        }
        return $code;
    }
    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function add_service_instance(string $id, Definition $definition, bool $is_simple_instance): string
    {
        $class = $this->dump_value($definition->get_class());
        if (str_starts_with($class, "'") && !str_contains($class, '$') && !preg_match('/^\'(?:\\\\{2})?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid class name for the "%s" service.', $class, $id));
        }
        $as_ghost_object = false;
        $is_proxy_candidate = $this->is_proxy_candidate($definition, $as_ghost_object, $id);
        $last_wither_index = null;
        foreach ($definition->get_method_calls() as $k => $call) {
            if ($call[2] ?? false) {
                $last_wither_index = $k;
            }
        }
        $should_share_inline = !$is_proxy_candidate && $definition->is_shared() && !isset($this->single_use_private_ids[$id]) && null === $last_wither_index;
        $service_accessor = \sprintf('$container->%s[%s]', $this->container->get_definition($id)->is_public() ? 'services' : 'privates', $this->do_export($id));
        $return = match (true) {
            $should_share_inline && !isset($this->circular_references[$id]) && $is_simple_instance => 'return ' . $service_accessor . ' = ',
            $should_share_inline && !isset($this->circular_references[$id]) => $service_accessor . ' = $instance = ',
            $should_share_inline || !$is_simple_instance => '$instance = ',
            default => 'return ',
        };
        $code = $this->add_new_instance($definition, '        ' . $return, $id, $as_ghost_object);
        if ($should_share_inline && isset($this->circular_references[$id])) {
            $code .= \sprintf("\n        if (isset(%s)) {\n            return %1\$s;\n        }\n\n        %s%1\$s = \$instance;\n", $service_accessor, $is_simple_instance ? 'return ' : '');
        }
        return $code;
    }
    private function is_trivial_instance(Definition $definition): bool
    {
        if ($definition->has_errors()) {
            return true;
        }
        if ($definition->is_synthetic() || $definition->get_file() || $definition->get_method_calls() || $definition->get_properties() || $definition->get_configurator()) {
            return false;
        }
        if ($definition->is_deprecated() || $definition->is_lazy() || $definition->get_factory() || 3 < \count($definition->get_arguments())) {
            return false;
        }
        foreach ($definition->get_arguments() as $arg) {
            if (!$arg) {
                continue;
            }
            if ($arg instanceof Parameter) {
                continue;
            }
            if (\is_array($arg) && 3 >= \count($arg)) {
                foreach ($arg as $k => $v) {
                    if ($this->dump_value($k) !== $this->dump_value($k, false)) {
                        return false;
                    }
                    if (!$v) {
                        continue;
                    }
                    if ($v instanceof Parameter) {
                        continue;
                    }
                    if ($v instanceof Reference && $this->container->has($id = (string) $v) && $this->container->find_definition($id)->is_synthetic()) {
                        continue;
                    }
                    if (!\is_scalar($v) || $this->dump_value($v) !== $this->dump_value($v, false)) {
                        return false;
                    }
                }
            } elseif ($arg instanceof Reference && $this->container->has($id = (string) $arg) && $this->container->find_definition($id)->is_synthetic()) {
                continue;
            } elseif (!\is_scalar($arg) || $this->dump_value($arg) !== $this->dump_value($arg, false)) {
                return false;
            }
        }
        return true;
    }
    private function add_service_method_calls(Definition $definition, string $variable_name, ?string $shared_non_lazy_id): string
    {
        $last_wither_index = null;
        foreach ($definition->get_method_calls() as $k => $call) {
            if ($call[2] ?? false) {
                $last_wither_index = $k;
            }
        }
        $calls = '';
        foreach ($definition->get_method_calls() as $k => $call) {
            $arguments = [];
            foreach ($call[1] as $i => $value) {
                $arguments[] = (\is_string($i) ? $i . ': ' : '') . $this->dump_value($value);
            }
            $wither_assignation = '';
            if ($call[2] ?? false) {
                if (null !== $shared_non_lazy_id && $last_wither_index === $k && 'instance' === $variable_name) {
                    $wither_assignation = \sprintf('$container->%s[\'%s\'] = ', $definition->is_public() ? 'services' : 'privates', $shared_non_lazy_id);
                }
                $wither_assignation .= \sprintf('$%s = ', $variable_name);
            }
            $calls .= $this->wrap_service_conditionals($call[1], \sprintf("        %s\$%s->%s(%s);\n", $wither_assignation, $variable_name, $call[0], implode(', ', $arguments)));
        }
        return $calls;
    }
    private function add_service_properties(Definition $definition, string $variable_name = 'instance'): string
    {
        $code = '';
        foreach ($definition->get_properties() as $name => $value) {
            $code .= \sprintf("        \$%s->%s = %s;\n", $variable_name, $name, $this->dump_value($value));
        }
        return $code;
    }
    private function add_service_configurator(Definition $definition, string $variable_name = 'instance'): string
    {
        if (!$callable = $definition->get_configurator()) {
            return '';
        }
        if (\is_array($callable)) {
            if ($callable[0] instanceof Reference || $callable[0] instanceof Definition && $this->definition_variables->offsetExists($callable[0])) {
                return \sprintf("        %s->%s(\$%s);\n", $this->dump_value($callable[0]), $callable[1], $variable_name);
            }
            $class = $this->dump_value($callable[0]);
            // If the class is a string we can optimize away
            if (str_starts_with($class, "'") && !str_contains($class, '$')) {
                return \sprintf("        %s::%s(\$%s);\n", $this->dump_literal_class($class), $callable[1], $variable_name);
            }
            if (str_starts_with($class, 'new ')) {
                return \sprintf("        (%s)->%s(\$%s);\n", $this->dump_value($callable[0]), $callable[1], $variable_name);
            }
            return \sprintf("        [%s, '%s'](\$%s);\n", $this->dump_value($callable[0]), $callable[1], $variable_name);
        }
        return \sprintf("        %s(\$%s);\n", $callable, $variable_name);
    }
    private function add_service(string $id, Definition $definition): array
    {
        $this->definition_variables = new \Spl_Object_Storage();
        $this->reference_variables = [];
        $this->variable_count = 0;
        $this->reference_variables[$id] = new Variable('instance');
        $return = [];
        if ($class = $definition->get_class()) {
            $class = $class instanceof Parameter ? '%' . $class . '%' : $this->container->resolve_env_placeholders($class);
            $return[] = \sprintf(str_starts_with($class, '%') ? '@return object A %1$s instance' : '@return \%s', ltrim($class, '\\'));
        } elseif ($factory = $definition->get_factory()) {
            if (\is_string($factory) && !str_starts_with($factory, '@=')) {
                $return[] = \sprintf('@return object An instance returned by %s()', $factory);
            } elseif (\is_array($factory) && (\is_string($factory[0]) || $factory[0] instanceof Definition || $factory[0] instanceof Reference)) {
                $class = $factory[0] instanceof Definition ? $factory[0]->get_class() : (string) $factory[0];
                $class = $class instanceof Parameter ? '%' . $class . '%' : $this->container->resolve_env_placeholders($class);
                $return[] = \sprintf('@return object An instance returned by %s::%s()', $class, $factory[1]);
            }
        }
        if ($definition->is_deprecated()) {
            if ($return && str_starts_with($return[\count($return) - 1], '@return')) {
                $return[] = '';
            }
            $deprecation = $definition->get_deprecation($id);
            $return[] = \sprintf('@deprecated %s', ($deprecation['package'] || $deprecation['version'] ? "Since {$deprecation['package']} {$deprecation['version']}: " : '') . $deprecation['message']);
        }
        $return = str_replace("\n     * \n", "\n     *\n", implode("\n     * ", $return));
        $return = $this->container->resolve_env_placeholders($return);
        $shared = $definition->is_shared() ? ' shared' : '';
        $public = $definition->is_public() ? 'public' : 'private';
        $autowired = $definition->is_autowired() ? ' autowired' : '';
        $as_file = $this->as_files && !$this->inline_factories && !$this->is_hot_path($definition);
        $method_name = $this->generate_method_name($id);
        if ($as_file || $definition->is_lazy()) {
            $lazy_initialization = ', $lazyLoad = true';
        } else {
            $lazy_initialization = '';
        }
        $code = <<<EOF
        
            /*{$this->doc_star}
             * Gets the {$public} '{$id}'{$shared}{$autowired} service.
             *
             * {$return}
        EOF;
        $code = str_replace('*/', ' ', $code) . <<<EOF
        
             */
            protected static function {$method_name}(\$container{$lazy_initialization})
            {
        
        EOF;
        if ($as_file) {
            $file = $method_name . '.php';
            $code = str_replace("protected static function {$method_name}(", 'public static function do(', $code);
        } else {
            $file = null;
        }
        if ($definition->has_errors() && $e = $definition->get_errors()) {
            $code .= \sprintf("        throw new RuntimeException(%s);\n", $this->export(reset($e)));
        } else {
            $this->service_calls = [];
            $this->inlined_definitions = $this->get_definitions_from_arguments([$definition], null, $this->service_calls);
            if ($definition->is_deprecated()) {
                $deprecation = $definition->get_deprecation($id);
                $code .= \sprintf("        trigger_deprecation(%s, %s, %s);\n\n", $this->export($deprecation['package']), $this->export($deprecation['version']), $this->export($deprecation['message']));
            } elseif ($definition->has_tag($this->hot_path_tag) || !$definition->has_tag($this->preload_tags[1])) {
                foreach ($this->inlined_definitions as $def) {
                    foreach ($this->get_classes($def, $id) as $class) {
                        $this->preload[$class] = $class;
                    }
                }
            }
            if (!$definition->is_shared()) {
                $factory = \sprintf('$container->factories%s[%s]', $definition->is_public() ? '' : "['service_container']", $this->do_export($id));
            }
            $as_ghost_object = false;
            if ($is_proxy_candidate = $this->is_proxy_candidate($definition, $as_ghost_object, $id)) {
                $definition = $is_proxy_candidate;
                if (!$definition->is_shared()) {
                    $code .= \sprintf('        %s ??= ', $factory);
                    if ($definition->is_public()) {
                        $code .= \sprintf("fn () => self::%s(\$container);\n\n", $as_file ? 'do' : $method_name);
                    } else {
                        $code .= \sprintf("self::%s(...);\n\n", $as_file ? 'do' : $method_name);
                    }
                }
                $lazy_load = $as_ghost_object ? '$proxy' : 'false';
                $factory_code = $as_file ? \sprintf('self::do($container, %s)', $lazy_load) : \sprintf('self::%s($container, %s)', $method_name, $lazy_load);
                $code .= $this->get_proxy_dumper()->get_proxy_factory_code($definition, $id, $factory_code);
            }
            $c = $this->add_service_include($id, $definition, null !== $is_proxy_candidate);
            if ('' !== $c && $is_proxy_candidate && !$definition->is_shared()) {
                $c = implode("\n", array_map(static fn($line): string => $line ? '    ' . $line : $line, explode("\n", $c)));
                $code .= "        static \$include = true;\n\n";
                $code .= "        if (\$include) {\n";
                $code .= $c;
                $code .= "            \$include = false;\n";
                $code .= "        }\n\n";
            } else {
                $code .= $c;
            }
            $c = $this->add_inline_service($id, $definition);
            if (!$is_proxy_candidate && !$definition->is_shared()) {
                $c = implode("\n", array_map(static fn($line): string => $line ? '    ' . $line : $line, explode("\n", $c)));
                $lazyload_initialization = $definition->is_lazy() ? ', $lazyLoad = true' : '';
                $c = \sprintf("        %s = function (\$container%s) {\n%s        };\n\n        return %1\$s(\$container);\n", $factory, $lazyload_initialization, $c);
            }
            $code .= $c;
        }
        $code .= "    }\n";
        $this->definition_variables = $this->inlined_definitions = null;
        $this->reference_variables = $this->service_calls = null;
        return [$file, $code];
    }
    private function add_inline_variables(string $id, Definition $definition, array $arguments, bool $for_constructor): string
    {
        $code = '';
        foreach ($arguments as $argument) {
            if (\is_array($argument)) {
                $code .= $this->add_inline_variables($id, $definition, $argument, $for_constructor);
            } elseif ($argument instanceof Reference) {
                $code .= $this->add_inline_reference($id, $definition, $argument, $for_constructor);
            } elseif ($argument instanceof Definition) {
                $code .= $this->add_inline_service($id, $definition, $argument, $for_constructor);
            }
        }
        return $code;
    }
    private function add_inline_reference(string $id, Definition $definition, string $target_id, bool $for_constructor): string
    {
        while ($this->container->has_alias($target_id)) {
            $target_id = (string) $this->container->get_alias($target_id);
        }
        [$call_count, $behavior] = $this->service_calls[$target_id];
        if ($id === $target_id) {
            return $this->add_inline_service($id, $definition, $definition);
        }
        if ('service_container' === $target_id || isset($this->reference_variables[$target_id])) {
            return '';
        }
        if ($this->container->has_definition($target_id) && ($def = $this->container->get_definition($target_id)) && !$def->is_shared()) {
            return '';
        }
        $has_self_ref = isset($this->circular_references[$id][$target_id]) && !isset($this->definition_variables[$definition]) && !($this->has_proxy_dumper && $definition->is_lazy());
        if ($has_self_ref && !$for_constructor && !$for_constructor = !$this->circular_references[$id][$target_id]) {
            $code = $this->add_inline_service($id, $definition, $definition);
        } else {
            $code = '';
        }
        if (isset($this->reference_variables[$target_id]) || 2 > $call_count && (!$has_self_ref || !$for_constructor)) {
            return $code;
        }
        $name = $this->get_next_variable_name();
        $this->reference_variables[$target_id] = new Variable($name);
        $reference = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE !== $behavior ? new Reference($target_id, $behavior) : null;
        $code .= \sprintf("        \$%s = %s;\n", $name, $this->get_service_call($target_id, $reference));
        if (!$has_self_ref || !$for_constructor) {
            return $code;
        }
        return $code . \sprintf(<<<'EOTXT'
        
                if (isset($container->%s[%s])) {
                    return $container->%1$s[%2$s];
                }
        
        EOTXT, $this->container->get_definition($id)->is_public() ? 'services' : 'privates', $this->do_export($id));
    }
    private function add_inline_service(string $id, Definition $definition, ?Definition $inline_def = null, bool $for_constructor = true): string
    {
        $code = '';
        if ($is_simple_instance = $is_root_instance = null === $inline_def) {
            foreach ($this->service_calls as $target_id => [, , $by_constructor]) {
                if ($by_constructor && isset($this->circular_references[$id][$target_id]) && !$this->circular_references[$id][$target_id] && !($this->has_proxy_dumper && $definition->is_lazy())) {
                    $code .= $this->add_inline_reference($id, $definition, $target_id, $for_constructor);
                }
            }
        }
        if (isset($this->definition_variables[$inline_def ??= $definition])) {
            return $code;
        }
        $arguments = [$inline_def->get_arguments(), $inline_def->get_factory()];
        $code .= $this->add_inline_variables($id, $definition, $arguments, $for_constructor);
        if ($arguments = array_filter([$inline_def->get_properties(), $inline_def->get_method_calls(), $inline_def->get_configurator()])) {
            $is_simple_instance = false;
        } elseif ($definition !== $inline_def && 2 > $this->inlined_definitions[$inline_def]) {
            return $code;
        }
        $as_ghost_object = false;
        $is_proxy_candidate = $this->is_proxy_candidate($inline_def, $as_ghost_object, $id);
        if (isset($this->definition_variables[$inline_def])) {
            $is_simple_instance = false;
        } else {
            $name = $definition === $inline_def ? 'instance' : $this->get_next_variable_name();
            $this->definition_variables[$inline_def] = new Variable($name);
            $code .= '' !== $code ? "\n" : '';
            if ('instance' === $name) {
                $code .= $this->add_service_instance($id, $definition, $is_simple_instance);
            } else {
                $code .= $this->add_new_instance($inline_def, '        $' . $name . ' = ', $id);
            }
            if ('' !== $inline = $this->add_inline_variables($id, $definition, $arguments, false)) {
                $code .= "\n" . $inline . "\n";
            } elseif ($arguments && 'instance' === $name) {
                $code .= "\n";
            }
            $code .= $this->add_service_properties($inline_def, $name);
            $code .= $this->add_service_method_calls($inline_def, $name, !$is_proxy_candidate && $inline_def->is_shared() && !isset($this->single_use_private_ids[$id]) ? $id : null);
            $code .= $this->add_service_configurator($inline_def, $name);
        }
        if (!$is_root_instance || $is_simple_instance) {
            return $code;
        }
        return $code . "\n        return \$instance;\n";
    }
    private function add_services(?array &$services = null): string
    {
        $public_services = $private_services = '';
        $definitions = $this->container->get_definitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->is_synthetic()) {
                $services[$id] = $this->add_service($id, $definition);
            } elseif ($definition->has_tag($this->hot_path_tag) || !$definition->has_tag($this->preload_tags[1])) {
                $services[$id] = null;
                foreach ($this->get_classes($definition, $id) as $class) {
                    $this->preload[$class] = $class;
                }
            }
        }
        foreach ($definitions as $id => $definition) {
            if (![$file, $code] = $services[$id]) {
                continue;
            }
            if (null !== $file) {
                continue;
            }
            if ($definition->is_public()) {
                $public_services .= $code;
            } elseif (!$this->is_trivial_instance($definition) || isset($this->located_ids[$id])) {
                $private_services .= $code;
            }
        }
        return $public_services . $private_services;
    }
    private function generate_service_files(array $services): iterable
    {
        $definitions = $this->container->get_definitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (([$file, $code] = $services[$id]) && null !== $file && ($definition->is_public() || !$this->is_trivial_instance($definition) || isset($this->located_ids[$id]))) {
                yield $file => [$code, $definition->has_tag($this->hot_path_tag) || !$definition->has_tag($this->preload_tags[1]) && !$definition->is_deprecated() && !$definition->has_errors()];
            }
        }
    }
    private function add_new_instance(Definition $definition, string $return = '', ?string $id = null, bool $as_ghost_object = false): string
    {
        $tail = $return ? str_repeat(')', substr_count($return, '(') - substr_count($return, ')')) . ";\n" : '';
        $arguments = [];
        if (Base_Service_Locator::class === $definition->get_class() && $definition->has_tag($this->service_locator_tag)) {
            foreach ($definition->get_argument(0) as $k => $argument) {
                $arguments[$k] = $argument->get_values()[0];
            }
            return $return . $this->dump_value(new Service_Locator_Argument($arguments)) . $tail;
        }
        foreach ($definition->get_arguments() as $i => $value) {
            $arguments[] = (\is_string($i) ? $i . ': ' : '') . $this->dump_value($value);
        }
        if ($callable = $definition->get_factory()) {
            if ('current' === $callable && [0] === array_keys($definition->get_arguments()) && \is_array($value) && [0] === array_keys($value)) {
                return $return . $this->dump_value($value[0]) . $tail;
            }
            if (['Closure', 'fromCallable'] === $callable) {
                $callable = $definition->get_argument(0);
                if ($callable instanceof Service_Closure_Argument) {
                    return $return . $this->dump_value($callable) . $tail;
                }
                $arguments = ['...'];
                if ($callable instanceof Reference || $callable instanceof Definition) {
                    $callable = [$callable, '__invoke'];
                }
            }
            if (\is_string($callable) && str_starts_with($callable, '@=')) {
                return $return . \sprintf('(($args = %s) ? (%s) : null)', $this->dump_value(new Service_Locator_Argument($definition->get_arguments())), $this->get_expression_language()->compile(substr($callable, 2), ['container' => 'container', 'args' => 'args'])) . $tail;
            }
            if (!\is_array($callable)) {
                return $return . \sprintf('%s(%s)', $this->dump_literal_class($this->dump_value($callable)), $arguments ? implode(', ', $arguments) : '') . $tail;
            }
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', (string) $callable[1])) {
                throw new RuntimeException(\sprintf('Cannot dump definition because of invalid factory method (%s).', $callable[1] ?: 'n/a'));
            }
            if (['...'] === $arguments && ('Closure' !== ($class = $definition->get_class() ?: 'Closure') || $definition->is_lazy() && ($callable[0] instanceof Reference || $callable[0] instanceof Definition && !$this->definition_variables->offsetExists($callable[0])))) {
                $initializer = 'fn () => ' . $this->dump_value($callable[0]);
                $this->preload[Lazy_Closure::class] = Lazy_Closure::class;
                return $return . Lazy_Closure::get_code($initializer, $callable, $class, $this->container, $id) . $tail;
            }
            if ($callable[0] instanceof Reference || $callable[0] instanceof Definition && $this->definition_variables->offsetExists($callable[0])) {
                return $return . \sprintf('%s->%s(%s)', $this->dump_value($callable[0]), $callable[1], $arguments ? implode(', ', $arguments) : '') . $tail;
            }
            $class = $this->dump_value($callable[0]);
            // If the class is a string we can optimize away
            if (str_starts_with($class, "'") && !str_contains($class, '$')) {
                if ("''" === $class) {
                    throw new RuntimeException(\sprintf('Cannot dump definition: "%s" service is defined to be created by a factory but is missing the service reference, did you forget to define the factory service id or class?', $id ? 'The "' . $id . '"' : 'inline'));
                }
                return $return . \sprintf('%s::%s(%s)', $this->dump_literal_class($class), $callable[1], $arguments ? implode(', ', $arguments) : '') . $tail;
            }
            if (str_starts_with($class, 'new ')) {
                return $return . \sprintf('(%s)->%s(%s)', $class, $callable[1], $arguments ? implode(', ', $arguments) : '') . $tail;
            }
            return $return . \sprintf("[%s, '%s'](%s)", $class, $callable[1], $arguments ? implode(', ', $arguments) : '') . $tail;
        }
        if (null === $class = $definition->get_class()) {
            throw new RuntimeException('Cannot dump definitions which have no class nor factory.');
        }
        if (!$as_ghost_object) {
            return $return . \sprintf('new %s(%s)', $this->dump_literal_class($this->dump_value($class)), implode(', ', $arguments)) . $tail;
        }
        if (!method_exists($this->container->get_parameter_bag()->resolve_value($class), '__construct')) {
            return $return . '$lazyLoad' . $tail;
        }
        return $return . \sprintf('($lazyLoad->__construct(%s) && false ?: $lazyLoad)', implode(', ', $arguments)) . $tail;
    }
    private function start_class(string $class, string $base_class, bool $has_proxy_classes): string
    {
        $namespace_line = !$this->as_files && $this->namespace ? "\nnamespace {$this->namespace};\n" : '';
        $code = <<<EOF
        <?php
        {$namespace_line}
        use Symfony\\Component\\DependencyInjection\\Argument\\RewindableGenerator;
        use Symfony\\Component\\DependencyInjection\\ContainerInterface;
        use Symfony\\Component\\DependencyInjection\\Container;
        use Symfony\\Component\\DependencyInjection\\Exception\\LogicException;
        use Symfony\\Component\\DependencyInjection\\Exception\\ParameterNotFoundException;
        use Symfony\\Component\\DependencyInjection\\Exception\\RuntimeException;
        use Symfony\\Component\\DependencyInjection\\ParameterBag\\FrozenParameterBag;
        use Symfony\\Component\\DependencyInjection\\ParameterBag\\ParameterBagInterface;
        
        /*{$this->doc_star}
         * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
         */
        class {$class} extends {$base_class}
        {
            private const DEPRECATED_PARAMETERS = [];
        
            private const NONEMPTY_PARAMETERS = [];
        
            protected \$parameters = [];
        
            public function __construct()
            {
        
        EOF;
        $code = str_replace("    private const DEPRECATED_PARAMETERS = [];\n\n", $this->add_deprecated_parameters(), $code);
        $code = str_replace("    private const NONEMPTY_PARAMETERS = [];\n\n", $this->add_non_empty_parameters(), $code);
        if ($this->as_files) {
            $code = str_replace('__construct()', '__construct(private array $buildParameters = [], protected string $containerDir = __DIR__)', $code);
            if (null !== $this->target_dir_regex) {
                $code = str_replace('$parameters = []', "\$targetDir;\n    protected \$parameters = []", $code);
                $code .= '        $this->targetDir = \dirname($containerDir);' . "\n";
            }
        }
        if ($this->needs_unset_parameter_bag()) {
            $code .= "        parent::__construct();\n";
            $code .= "        unset(\$this->parameterBag);\n\n";
        }
        if ($this->container->get_parameter_bag()->all()) {
            $code .= "        \$this->parameters = \$this->getDefaultParameters();\n\n";
        }
        $code .= "        \$this->services = \$this->privates = [];\n";
        $code .= $this->add_synthetic_ids();
        $code .= $this->add_method_map();
        $code .= $this->as_files && !$this->inline_factories ? $this->add_file_map() : '';
        $code .= $this->add_aliases();
        $code .= $this->add_inline_requires($has_proxy_classes);
        $code .= <<<EOF
            }
        
            public function compile(): void
            {
                throw new LogicException('You cannot compile a dumped container that was already compiled.');
            }
        
            public function isCompiled(): bool
            {
                return true;
            }
        
        EOF;
        $code .= $this->add_removed_ids();
        if ($this->as_files && !$this->inline_factories) {
            $code .= <<<'EOF'
            
                protected function load($file, $lazyLoad = true): mixed
                {
                    if (class_exists($class = __NAMESPACE__.'\\'.$file, false)) {
                        return $class::do($this, $lazyLoad);
                    }
            
                    if ('.' === $file[-4]) {
                        $class = substr($class, 0, -4);
                    } else {
                        $file .= '.php';
                    }
            
                    $service = require $this->containerDir.\DIRECTORY_SEPARATOR.$file;
            
                    return class_exists($class, false) ? $class::do($this, $lazyLoad) : $service;
                }
            
            EOF;
        }
        foreach ($this->container->get_definitions() as $definition) {
            if (!$definition->is_lazy()) {
                continue;
            }
            if (!$this->has_proxy_dumper) {
                continue;
            }
            if ($this->as_files && !$this->inline_factories) {
                $proxy_loader = "class_exists(\$class, false) || require __DIR__.'/'.\$class.'.php';\n\n        ";
            } else {
                $proxy_loader = '';
            }
            $code .= <<<EOF
            
                protected function createProxy(\$class, \\Closure \$factory)
                {
                    {$proxy_loader}return \$factory();
                }
            
            EOF;
            break;
        }
        return $code;
    }
    private function add_synthetic_ids(): string
    {
        $code = '';
        $definitions = $this->container->get_definitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if ($definition->is_synthetic() && 'service_container' !== $id) {
                $code .= '            ' . $this->do_export($id) . " => true,\n";
            }
        }
        return $code ? "        \$this->syntheticIds = [\n{$code}        ];\n" : '';
    }
    private function add_removed_ids(): string
    {
        $ids = $this->container->get_removed_ids();
        foreach ($this->container->get_definitions() as $id => $definition) {
            if (!$definition->is_public() && '.' !== ($id[0] ?? '-')) {
                $ids[$id] = true;
            }
        }
        if (!$ids) {
            return '';
        }
        if ($this->as_files) {
            $code = "require \$this->containerDir.\\DIRECTORY_SEPARATOR.'removed-ids.php'";
        } else {
            $code = '';
            $ids = array_keys($ids);
            sort($ids);
            foreach ($ids as $id) {
                if (preg_match(File_Loader::ANONYMOUS_ID_REGEXP, $id)) {
                    continue;
                }
                $code .= '            ' . $this->do_export($id) . " => true,\n";
            }
            $code = "[\n{$code}        ]";
        }
        return <<<EOF
        
            public function getRemovedIds(): array
            {
                return {$code};
            }
        
        EOF;
    }
    private function add_deprecated_parameters(): string
    {
        if (!($bag = $this->container->get_parameter_bag()) instanceof Parameter_Bag) {
            return '';
        }
        if (!$deprecated = $bag->all_deprecated()) {
            return '';
        }
        $code = '';
        ksort($deprecated);
        foreach ($deprecated as $param => $deprecation) {
            $code .= '        ' . $this->do_export($param) . ' => [' . implode(', ', array_map($this->do_export(...), $deprecation)) . "],\n";
        }
        return "    private const DEPRECATED_PARAMETERS = [\n{$code}    ];\n\n";
    }
    private function add_non_empty_parameters(): string
    {
        if (!($bag = $this->container->get_parameter_bag()) instanceof Parameter_Bag) {
            return '';
        }
        if (!$non_empty = $bag->all_non_empty()) {
            return '';
        }
        $code = '';
        ksort($non_empty);
        foreach ($non_empty as $param => $message) {
            $code .= '        ' . $this->do_export($param) . ' => ' . $this->do_export($message) . ",\n";
        }
        return "    private const NONEMPTY_PARAMETERS = [\n{$code}    ];\n\n";
    }
    private function add_method_map(): string
    {
        $code = '';
        $definitions = $this->container->get_definitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->is_synthetic() && $definition->is_public() && (!$this->as_files || $this->inline_factories || $this->is_hot_path($definition))) {
                $code .= '            ' . $this->do_export($id) . ' => ' . $this->do_export($this->generate_method_name($id)) . ",\n";
            }
        }
        $aliases = $this->container->get_aliases();
        foreach ($aliases as $alias => $id) {
            if (!$id->is_deprecated()) {
                continue;
            }
            $code .= '            ' . $this->do_export($alias) . ' => ' . $this->do_export($this->generate_method_name($alias)) . ",\n";
        }
        return $code ? "        \$this->methodMap = [\n{$code}        ];\n" : '';
    }
    private function add_file_map(): string
    {
        $code = '';
        $definitions = $this->container->get_definitions();
        ksort($definitions);
        foreach ($definitions as $id => $definition) {
            if (!$definition->is_synthetic() && $definition->is_public() && !$this->is_hot_path($definition)) {
                $code .= \sprintf("            %s => '%s',\n", $this->do_export($id), $this->generate_method_name($id));
            }
        }
        return $code ? "        \$this->fileMap = [\n{$code}        ];\n" : '';
    }
    private function add_aliases(): string
    {
        if (!$aliases = $this->container->get_aliases()) {
            return "\n        \$this->aliases = [];\n";
        }
        $code = "        \$this->aliases = [\n";
        ksort($aliases);
        foreach ($aliases as $alias => $id) {
            if ($id->is_deprecated()) {
                continue;
            }
            $id = (string) $id;
            while (isset($aliases[$id])) {
                $id = (string) $aliases[$id];
            }
            $code .= '            ' . $this->do_export($alias) . ' => ' . $this->do_export($id) . ",\n";
        }
        return $code . "        ];\n";
    }
    private function add_deprecated_aliases(): string
    {
        $code = '';
        $aliases = $this->container->get_aliases();
        foreach ($aliases as $alias => $definition) {
            if (!$definition->is_deprecated()) {
                continue;
            }
            $public = $definition->is_public() ? 'public' : 'private';
            $id = (string) $definition;
            $method_name_alias = $this->generate_method_name($alias);
            $id_exported = $this->export($id);
            $deprecation = $definition->get_deprecation($alias);
            $package_exported = $this->export($deprecation['package']);
            $version_exported = $this->export($deprecation['version']);
            $message_exported = $this->export($deprecation['message']);
            $code .= <<<EOF
            
                /*{$this->doc_star}
                 * Gets the {$public} '{$alias}' alias.
                 *
                 * @return object The "{$id}" service.
                 */
                protected static function {$method_name_alias}(\$container)
                {
                    trigger_deprecation({$package_exported}, {$version_exported}, {$message_exported});
            
                    return \$container->get({$id_exported});
                }
            
            EOF;
        }
        return $code;
    }
    private function add_inline_requires(bool $has_proxy_classes): string
    {
        $lineage = [];
        $hot_path_services = $this->hot_path_tag && $this->inline_requires ? $this->container->find_tagged_service_ids($this->hot_path_tag) : [];
        foreach ($hot_path_services as $id => $tags) {
            $definition = $this->container->get_definition($id);
            if ($definition->is_lazy() && $this->has_proxy_dumper) {
                continue;
            }
            $inlined_definitions = $this->get_definitions_from_arguments([$definition]);
            foreach ($inlined_definitions as $def) {
                foreach ($this->get_classes($def, $id) as $class) {
                    $this->collect_lineage($class, $lineage);
                }
            }
        }
        $code = '';
        foreach ($lineage as $file) {
            if (!isset($this->inlined_requires[$file])) {
                $this->inlined_requires[$file] = true;
                $code .= \sprintf("\n            include_once %s;", $file);
            }
        }
        if ($has_proxy_classes) {
            $code .= "\n            include_once __DIR__.'/proxy-classes.php';";
        }
        return $code ? \sprintf("\n        \$this->privates['service_container'] = static function (\$container) {%s\n        };\n", $code) : '';
    }
    private function needs_unset_parameter_bag(): bool
    {
        if (Container::class === $this->base_class) {
            return false;
        }
        $r = $this->container->get_reflection_class($this->base_class, false);
        return null !== $r && null !== ($constructor = $r->get_constructor()) && 0 === $constructor->get_number_of_required_parameters() && Container::class !== $constructor->class;
    }
    private function add_default_parameters_method(): string
    {
        $bag = $this->container->get_parameter_bag();
        if (!$bag->all() && (!$bag instanceof Parameter_Bag || !$bag->all_non_empty()) && !$this->needs_unset_parameter_bag()) {
            return '';
        }
        $php = [];
        $dynamic_php = [];
        foreach ($bag->all() as $key => $value) {
            if ($key !== $resolved_key = $this->container->resolve_env_placeholders($key)) {
                throw new InvalidArgumentException(\sprintf('Parameter name cannot use env parameters: "%s".', $resolved_key));
            }
            $has_enum = false;
            $export = $this->export_parameters([$value], '', 12, $has_enum);
            $export = explode('0 => ', substr(rtrim($export, " ]\n"), 2, -1), 2);
            if ($has_enum || preg_match("/\\\$container->(?:getEnv\\('(?:[-.\\w\\\\]*+:)*+[\\w.]*+'\\)|targetDir\\.'')/", $export[1])) {
                $dynamic_php[$key] = \sprintf('%s%s => %s,', $export[0], $this->export($key), $export[1]);
                $this->dynamic_parameters[$key] = true;
            } else {
                $php[] = \sprintf('%s%s => %s,', $export[0], $this->export($key), $export[1]);
            }
        }
        $parameters = \sprintf("[\n%s\n%s]", implode("\n", $php), str_repeat(' ', 8));
        $code = <<<'EOF'
        
            public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
            {
                if (isset(self::DEPRECATED_PARAMETERS[$name])) {
                    trigger_deprecation(...self::DEPRECATED_PARAMETERS[$name]);
                }
        
                if (\array_key_exists($name, $this->buildParameters)) {
                    return $this->buildParameters[$name];
                }
        
                if (isset($this->loadedDynamicParameters[$name])) {
                    $value = $this->loadedDynamicParameters[$name] ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
                } elseif (\array_key_exists($name, $this->parameters) && '.' !== ($name[0] ?? '')) {
                    $value = $this->parameters[$name];
                } else {
                    throw new ParameterNotFoundException($name, extraMessage: self::NONEMPTY_PARAMETERS[$name] ?? null);
                }
        
                if (isset(self::NONEMPTY_PARAMETERS[$name]) && (null === $value || '' === $value || [] === $value)) {
                    throw new \Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException(self::NONEMPTY_PARAMETERS[$name]);
                }
        
                return $value;
            }
        
            public function hasParameter(string $name): bool
            {
                if (\array_key_exists($name, $this->buildParameters)) {
                    return true;
                }
        
                return \array_key_exists($name, $this->parameters) || isset($this->loadedDynamicParameters[$name]);
            }
        
            public function setParameter(string $name, $value): void
            {
                throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
            }
        
            public function getParameterBag(): ParameterBagInterface
            {
                if (!isset($this->parameterBag)) {
                    $parameters = $this->parameters;
                    foreach ($this->loadedDynamicParameters as $name => $loaded) {
                        $parameters[$name] = $loaded ? $this->dynamicParameters[$name] : $this->getDynamicParameter($name);
                    }
                    foreach ($this->buildParameters as $name => $value) {
                        $parameters[$name] = $value;
                    }
                    $this->parameterBag = new FrozenParameterBag($parameters, self::DEPRECATED_PARAMETERS, self::NONEMPTY_PARAMETERS);
                }
        
                return $this->parameterBag;
            }
        
        EOF;
        if (!$this->as_files) {
            $code = preg_replace('/^.*buildParameters.*\n.*\n.*\n\n?/m', '', $code);
        }
        if (!$bag instanceof Parameter_Bag || !$bag->all_deprecated()) {
            $code = preg_replace("/\n.*DEPRECATED_PARAMETERS.*\n.*\n.*\n/m", '', (string) $code, 1);
            $code = str_replace(', self::DEPRECATED_PARAMETERS', ', []', $code);
        }
        if (!$bag instanceof Parameter_Bag || !$bag->all_non_empty()) {
            $code = str_replace(', extraMessage: self::NONEMPTY_PARAMETERS[$name] ?? null', '', $code);
            $code = str_replace(', self::NONEMPTY_PARAMETERS', '', $code);
            $code = preg_replace("/\n.*NONEMPTY_PARAMETERS.*\n.*\n.*\n/m", '', $code, 1);
        }
        if ($dynamic_php) {
            $loaded_dynamic_parameters = $this->export_parameters(array_combine(array_keys($dynamic_php), array_fill(0, \count($dynamic_php), false)), '', 8);
            $get_dynamic_parameter = <<<'EOF'
                    $container = $this;
                    $value = match ($name) {
            %s
                        default => throw new ParameterNotFoundException($name),
                    };
                    $this->loadedDynamicParameters[$name] = true;
            
                    return $this->dynamicParameters[$name] = $value;
            EOF;
            $get_dynamic_parameter = \sprintf($get_dynamic_parameter, implode("\n", $dynamic_php));
        } else {
            $loaded_dynamic_parameters = '[]';
            $get_dynamic_parameter = str_repeat(' ', 8) . 'throw new ParameterNotFoundException($name);';
        }
        return $code . <<<EOF
        
            private \$loadedDynamicParameters = {$loaded_dynamic_parameters};
            private \$dynamicParameters = [];
        
            private function getDynamicParameter(string \$name)
            {
        {$get_dynamic_parameter}
            }
        
            protected function getDefaultParameters(): array
            {
                return {$parameters};
            }
        
        EOF;
    }
    /**
     * @throws InvalidArgumentException
     */
    private function export_parameters(array $parameters, string $path = '', int $indent = 12, bool &$has_enum = false): string
    {
        $php = [];
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $value = $this->export_parameters($value, $path . '/' . $key, $indent + 4, $has_enum);
            } elseif ($value instanceof Argument_Interface) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain special arguments. "%s" found in "%s".', get_debug_type($value), $path . '/' . $key));
            } elseif ($value instanceof Variable) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain variable references. Variable "%s" found in "%s".', $value, $path . '/' . $key));
            } elseif ($value instanceof Definition) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain service definitions. Definition for "%s" found in "%s".', $value->get_class(), $path . '/' . $key));
            } elseif ($value instanceof Reference) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain references to other services (reference to service "%s" found in "%s").', $value, $path . '/' . $key));
            } elseif ($value instanceof Expression) {
                throw new InvalidArgumentException(\sprintf('You cannot dump a container with parameters that contain expressions. Expression "%s" found in "%s".', $value, $path . '/' . $key));
            } elseif ($value instanceof \Unit_Enum) {
                $has_enum = true;
                $value = \sprintf('\%s::%s', $value::class, $value->name);
            } else {
                $value = $this->export($value);
            }
            $php[] = \sprintf('%s%s => %s,', str_repeat(' ', $indent), $this->export($key), $value);
        }
        return \sprintf("[\n%s\n%s]", implode("\n", $php), str_repeat(' ', $indent - 4));
    }
    private function end_class(): string
    {
        return <<<'EOF'
        }
        
        EOF;
    }
    private function wrap_service_conditionals(mixed $value, string $code): string
    {
        if (!$condition = $this->get_service_conditionals($value)) {
            return $code;
        }
        // re-indent the wrapped code
        $code = implode("\n", array_map(static fn($line): string => $line ? '    ' . $line : $line, explode("\n", $code)));
        return \sprintf("        if (%s) {\n%s        }\n", $condition, $code);
    }
    private function get_service_conditionals(mixed $value): string
    {
        $conditions = [];
        foreach (Container_Builder::get_initialized_conditionals($value) as $service) {
            if (!$this->container->has_definition($service)) {
                return 'false';
            }
            $conditions[] = \sprintf('isset($container->%s[%s])', $this->container->get_definition($service)->is_public() ? 'services' : 'privates', $this->do_export($service));
        }
        foreach (Container_Builder::get_service_conditionals($value) as $service) {
            if ($this->container->has_definition($service) && !$this->container->get_definition($service)->is_public()) {
                continue;
            }
            $conditions[] = \sprintf('$container->has(%s)', $this->do_export($service));
        }
        if (!$conditions) {
            return '';
        }
        return implode(' && ', $conditions);
    }
    private function get_definitions_from_arguments(array $arguments, ?\Spl_Object_Storage $definitions = null, array &$calls = [], ?bool $by_constructor = null): \Spl_Object_Storage
    {
        $definitions ??= new \Spl_Object_Storage();
        foreach ($arguments as $argument) {
            if (\is_array($argument)) {
                $this->get_definitions_from_arguments($argument, $definitions, $calls, $by_constructor);
            } elseif ($argument instanceof Reference) {
                $id = (string) $argument;
                while ($this->container->has_alias($id)) {
                    $id = (string) $this->container->get_alias($id);
                }
                if (!isset($calls[$id])) {
                    $calls[$id] = [0, $argument->get_invalid_behavior(), $by_constructor];
                } else {
                    $calls[$id][1] = min($calls[$id][1], $argument->get_invalid_behavior());
                }
                ++$calls[$id][0];
            } elseif (!$argument instanceof Definition) {
                // no-op
            } elseif (isset($definitions[$argument])) {
                $definitions[$argument] = 1 + $definitions[$argument];
            } else {
                $definitions[$argument] = 1;
                $arguments = [$argument->get_arguments(), $argument->get_factory()];
                $this->get_definitions_from_arguments($arguments, $definitions, $calls, null === $by_constructor || $by_constructor);
                $arguments = [$argument->get_properties(), $argument->get_method_calls(), $argument->get_configurator()];
                $this->get_definitions_from_arguments($arguments, $definitions, $calls, null !== $by_constructor && $by_constructor);
            }
        }
        return $definitions;
    }
    /**
     * @throws RuntimeException
     */
    private function dump_value(mixed $value, bool $interpolate = true): string
    {
        if (\is_array($value)) {
            if ($value && $interpolate && false !== $param = array_search($value, $this->container->get_parameter_bag()->all(), true)) {
                return $this->dump_value("%{$param}%");
            }
            $is_list = array_is_list($value);
            $code = [];
            foreach ($value as $k => $v) {
                $code[] = $is_list ? $this->dump_value($v, $interpolate) : \sprintf('%s => %s', $this->dump_value($k, $interpolate), $this->dump_value($v, $interpolate));
            }
            return \sprintf('[%s]', implode(', ', $code));
        }
        if ($value instanceof Argument_Interface) {
            $scope = [$this->definition_variables, $this->reference_variables];
            $this->definition_variables = $this->reference_variables = null;
            try {
                if ($value instanceof Service_Closure_Argument) {
                    $value = $value->get_values()[0];
                    $code = $this->dump_value($value, $interpolate);
                    $returned_type = '';
                    if ($value instanceof Typed_Reference) {
                        $type = $value->get_type();
                        $nullable = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE >= $value->get_invalid_behavior() ? '' : '?';
                        if ('?' === ($type[0] ?? '')) {
                            $type = substr($type, 1);
                            $nullable = '?';
                        }
                        $returned_type = \sprintf(': %s\%s', $nullable, str_replace(['|', '&'], ['|\\', '&\\'], $type));
                    }
                    $attribute = '';
                    if ($value instanceof Reference) {
                        $attribute = 'name: ' . $this->dump_value((string) $value, $interpolate);
                        if ($this->container->has_definition($value) && ($class = $this->container->find_definition($value)->get_class()) && $class !== (string) $value) {
                            $attribute .= ', class: ' . $this->dump_value($class, $interpolate);
                        }
                        $attribute = \sprintf('#[\Closure(%s)] ', $attribute);
                    }
                    return \sprintf('%sfn ()%s => %s', $attribute, $returned_type, $code);
                }
                if ($value instanceof Iterator_Argument) {
                    if (!$values = $value->get_values()) {
                        return 'new RewindableGenerator(fn () => new \EmptyIterator(), 0)';
                    }
                    $code = [];
                    $code[] = 'new RewindableGenerator(function () use ($container) {';
                    $operands = [0];
                    foreach ($values as $k => $v) {
                        ($c = $this->get_service_conditionals($v)) ? $operands[] = "(int) ({$c})" : ++$operands[0];
                        $v = $this->wrap_service_conditionals($v, \sprintf("        yield %s => %s;\n", $this->dump_value($k, $interpolate), $this->dump_value($v, $interpolate)));
                        foreach (explode("\n", $v) as $v) {
                            if ($v) {
                                $code[] = '    ' . $v;
                            }
                        }
                    }
                    $code[] = \sprintf('        }, %s)', \count($operands) > 1 ? 'fn () => ' . implode(' + ', $operands) : $operands[0]);
                    return implode("\n", $code);
                }
                if ($value instanceof Service_Locator_Argument) {
                    $service_map = '';
                    $service_types = '';
                    foreach ($value->get_values() as $k => $v) {
                        if (!$v instanceof Reference) {
                            $service_map .= \sprintf("\n            %s => [%s],", $this->export($k), $this->dump_value($v));
                            $service_types .= \sprintf("\n            %s => '?',", $this->export($k));
                            continue;
                        }
                        $id = (string) $v;
                        while ($this->container->has_alias($id)) {
                            $id = (string) $this->container->get_alias($id);
                        }
                        $definition = $this->container->get_definition($id);
                        $load = !($definition->has_errors() && $e = $definition->get_errors()) ? $this->as_files && !$this->inline_factories && !$this->is_hot_path($definition) : reset($e);
                        $service_map .= \sprintf("\n            %s => [%s, %s, %s, %s],", $this->export($k), $this->export($definition->is_shared() ? $definition->is_public() ? 'services' : 'privates' : false), $this->do_export($id), $this->export(Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE !== $v->get_invalid_behavior() && !\is_string($load) ? $this->generate_method_name($id) : null), $this->export($load));
                        $service_types .= \sprintf("\n            %s => %s,", $this->export($k), $this->export($v instanceof Typed_Reference ? $v->get_type() : '?'));
                        $this->located_ids[$id] = true;
                    }
                    $this->add_get_service = true;
                    return \sprintf('new \%s($container->getService ??= $container->getService(...), [%s%s], [%s%s])', Service_Locator::class, $service_map, $service_map ? "\n        " : '', $service_types, $service_types ? "\n        " : '');
                }
            } finally {
                [$this->definition_variables, $this->reference_variables] = $scope;
            }
        } elseif ($value instanceof Definition) {
            if ($value->has_errors() && $e = $value->get_errors()) {
                return \sprintf('throw new RuntimeException(%s)', $this->export(reset($e)));
            }
            if ($this->definition_variables?->offsetExists($value)) {
                return $this->dump_value($this->definition_variables[$value], $interpolate);
            }
            if ($value->get_method_calls()) {
                throw new RuntimeException('Cannot dump definitions which have method calls.');
            }
            if ($value->get_properties()) {
                throw new RuntimeException('Cannot dump definitions which have properties.');
            }
            if (null !== $value->get_configurator()) {
                throw new RuntimeException('Cannot dump definitions which have a configurator.');
            }
            return $this->add_new_instance($value);
        } elseif ($value instanceof Variable) {
            return '$' . $value;
        } elseif ($value instanceof Reference) {
            $id = (string) $value;
            while ($this->container->has_alias($id)) {
                $id = (string) $this->container->get_alias($id);
            }
            if (null !== $this->reference_variables && isset($this->reference_variables[$id])) {
                return $this->dump_value($this->reference_variables[$id], $interpolate);
            }
            return $this->get_service_call($id, $value);
        } elseif ($value instanceof Expression) {
            return $this->get_expression_language()->compile((string) $value, ['container' => 'container']);
        } elseif ($value instanceof Parameter) {
            return $this->dump_parameter($value);
        } elseif (true === $interpolate && \is_string($value)) {
            if (preg_match('/^%([^%]+)%$/', $value, $match)) {
                // we do this to deal with non string values (Boolean, integer, ...)
                // the preg_replace_callback converts them to strings
                return $this->dump_parameter($match[1]);
            }
            $replace_parameters = fn($match): string => "'." . $this->dump_parameter($match[2]) . ".'";
            return str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replace_parameters, (string) $this->export($value)));
        } elseif ($value instanceof \Unit_Enum) {
            return \sprintf('\%s::%s', $value::class, $value->name);
        } elseif ($value instanceof Abstract_Argument) {
            throw new RuntimeException($value->get_text_with_context());
        } elseif (\is_object($value) || \is_resource($value)) {
            throw new RuntimeException(\sprintf('Unable to dump a service container if a parameter is an object or a resource, got "%s".', get_debug_type($value)));
        }
        return $this->export($value);
    }
    /**
     * Dumps a string to a literal (aka PHP Code) class value.
     *
     * @throws RuntimeException
     */
    private function dump_literal_class(string $class): string
    {
        if (str_contains($class, '$')) {
            return \sprintf('${($_ = %s) && false ?: "_"}', $class);
        }
        if (!str_starts_with($class, "'") || !preg_match('/^\'(?:\\\\{2})?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\{2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*\'$/', $class)) {
            throw new RuntimeException(\sprintf('Cannot dump definition because of invalid class name (%s).', $class ?: 'n/a'));
        }
        $class = substr(str_replace('\\\\', '\\', $class), 1, -1);
        return str_starts_with($class, '\\') ? $class : '\\' . $class;
    }
    private function dump_parameter(string $name): string
    {
        if (!$this->container->has_parameter($name) || ($this->dynamic_parameters[$name] ?? false)) {
            return \sprintf('$container->getParameter(%s)', $this->do_export($name));
        }
        $value = $this->container->get_parameter($name);
        $dumped_value = $this->dump_value($value, false);
        if (!$value || !\is_array($value)) {
            return $dumped_value;
        }
        return \sprintf('$container->parameters[%s]', $this->do_export($name));
    }
    private function get_service_call(string $id, ?Reference $reference = null): string
    {
        while ($this->container->has_alias($id)) {
            $id = (string) $this->container->get_alias($id);
        }
        if ('service_container' === $id) {
            return '$container';
        }
        if ($this->container->has_definition($id) && $definition = $this->container->get_definition($id)) {
            if ($definition->is_synthetic()) {
                $code = \sprintf('$container->get(%s%s)', $this->do_export($id), null !== $reference ? ', ' . $reference->get_invalid_behavior() : '');
            } elseif (null !== $reference && Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE === $reference->get_invalid_behavior()) {
                $code = 'null';
                if (!$definition->is_shared()) {
                    return $code;
                }
            } elseif ($this->is_trivial_instance($definition)) {
                if ($definition->has_errors() && $e = $definition->get_errors()) {
                    return \sprintf('throw new RuntimeException(%s)', $this->export(reset($e)));
                }
                $code = $this->add_new_instance($definition, '', $id);
                if ($definition->is_shared() && !isset($this->single_use_private_ids[$id])) {
                    return \sprintf('($container->%s[%s] ??= %s)', $definition->is_public() ? 'services' : 'privates', $this->do_export($id), $code);
                }
                $code = "({$code})";
            } else {
                $code = $this->as_files && !$this->inline_factories && !$this->is_hot_path($definition) ? "\$container->load('%s')" : 'self::%s($container)';
                $code = \sprintf($code, $this->generate_method_name($id));
                if (!$definition->is_shared()) {
                    $factory = \sprintf('$container->factories%s[%s]', $definition->is_public() ? '' : "['service_container']", $this->do_export($id));
                    $code = \sprintf('(isset(%s) ? %1$s($container) : %s)', $factory, $code);
                }
            }
            if ($definition->is_shared() && !isset($this->single_use_private_ids[$id])) {
                return \sprintf('($container->%s[%s] ?? %s)', $definition->is_public() ? 'services' : 'privates', $this->do_export($id), $code);
            }
            return $code;
        }
        if (null !== $reference && Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE === $reference->get_invalid_behavior()) {
            return 'null';
        }
        if (null !== $reference && Container_Interface::EXCEPTION_ON_INVALID_REFERENCE < $reference->get_invalid_behavior()) {
            $code = \sprintf('$container->get(%s, ContainerInterface::NULL_ON_INVALID_REFERENCE)', $this->do_export($id));
        } else {
            $code = \sprintf('$container->get(%s)', $this->do_export($id));
        }
        return \sprintf('($container->services[%s] ?? %s)', $this->do_export($id), $code);
    }
    /**
     * Initializes the method names map to avoid conflicts with the Container methods.
     */
    private function initialize_method_names_map(string $class): void
    {
        $this->service_id_to_method_name_map = [];
        $this->used_method_names = [];
        if ($reflection_class = $this->container->get_reflection_class($class)) {
            foreach ($reflection_class->get_methods() as $method) {
                $this->used_method_names[strtolower($method->get_name())] = true;
            }
        }
    }
    /**
     * @throws InvalidArgumentException
     */
    private function generate_method_name(string $id): string
    {
        if (isset($this->service_id_to_method_name_map[$id])) {
            return $this->service_id_to_method_name_map[$id];
        }
        $i = strrpos($id, '\\');
        $name = Container::camelize(false !== $i && isset($id[1 + $i]) ? substr($id, 1 + $i) : $id);
        $name = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/', '', $name);
        $method_name = 'get' . $name . 'Service';
        $suffix = 1;
        while (isset($this->used_method_names[strtolower($method_name)])) {
            ++$suffix;
            $method_name = 'get' . $name . $suffix . 'Service';
        }
        $this->service_id_to_method_name_map[$id] = $method_name;
        $this->used_method_names[strtolower($method_name)] = true;
        return $method_name;
    }
    private function get_next_variable_name(): string
    {
        $first_chars = self::FIRST_CHARS;
        $first_chars_length = \strlen($first_chars);
        $non_first_chars = self::NON_FIRST_CHARS;
        $non_first_chars_length = \strlen($non_first_chars);
        while (true) {
            $name = '';
            $i = $this->variable_count;
            $name .= $first_chars[$i % $first_chars_length];
            $i = (int) ($i / $first_chars_length);
            while ($i > 0) {
                --$i;
                $name .= $non_first_chars[$i % $non_first_chars_length];
                $i = (int) ($i / $non_first_chars_length);
            }
            ++$this->variable_count;
            // check that the name is not reserved
            if (\in_array($name, $this->reserved_variables, true)) {
                continue;
            }
            return $name;
        }
    }
    private function get_expression_language(): Expression_Language
    {
        if (!isset($this->expression_language)) {
            if (!class_exists(\Symfony\Component\Expression_Language\Expression_Language::class)) {
                throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
            }
            $providers = $this->container->get_expression_language_providers();
            $this->expression_language = new Expression_Language(null, $providers, function ($arg): string {
                $id = '""' === substr_replace($arg, '', 1, -1) ? stripcslashes(substr($arg, 1, -1)) : null;
                if (null !== $id && ($this->container->has_alias($id) || $this->container->has_definition($id))) {
                    return $this->get_service_call($id);
                }
                return \sprintf('$container->get(%s)', $arg);
            });
            if ($this->container->is_tracking_resources()) {
                foreach ($providers as $provider) {
                    $this->container->add_object_resource($provider);
                }
            }
        }
        return $this->expression_language;
    }
    private function is_hot_path(Definition $definition): bool
    {
        return $this->hot_path_tag && $definition->has_tag($this->hot_path_tag) && !$definition->is_deprecated();
    }
    private function is_single_use_private_node(Service_Reference_Graph_Node $node): bool
    {
        if ($node->get_value()->is_public()) {
            return false;
        }
        $ids = [];
        foreach ($node->get_in_edges() as $edge) {
            if (!$value = $edge->get_source_node()->get_value()) {
                continue;
            }
            if ($edge->is_lazy() || !$value instanceof Definition || !$value->is_shared() || $edge->is_from_multi_use_argument()) {
                return false;
            }
            // When the source node is a proxy or ghost, it will construct its references only when the node itself is initialized.
            // Since the node can be cloned before being fully initialized, we do not know how often its references are used.
            if ($this->get_proxy_dumper()->is_proxy_candidate($value)) {
                return false;
            }
            $ids[$edge->get_source_node()->get_id()] = true;
        }
        return 1 === \count($ids);
    }
    private function export(mixed $value): mixed
    {
        if (null !== $this->target_dir_regex && \is_string($value) && preg_match($this->target_dir_regex, $value, $matches, \PREG_OFFSET_CAPTURE)) {
            $suffix = $matches[0][1] + \strlen($matches[0][0]);
            $matches[0][1] += \strlen($matches[1][0]);
            $prefix = $matches[0][1] ? $this->do_export(substr($value, 0, $matches[0][1]), true) . '.' : '';
            if ('\\' === \DIRECTORY_SEPARATOR && isset($value[$suffix])) {
                $cookie = '\\' . random_int(100000, \PHP_INT_MAX);
                $suffix = '.' . $this->do_export(str_replace('\\', $cookie, substr($value, $suffix)), true);
                $suffix = str_replace('\\' . $cookie, "'.\\DIRECTORY_SEPARATOR.'", $suffix);
            } else {
                $suffix = isset($value[$suffix]) ? '.' . $this->do_export(substr($value, $suffix), true) : '';
            }
            $dirname = $this->as_files ? '$container->containerDir' : '__DIR__';
            $offset = 2 + $this->target_dir_max_matches - \count($matches);
            if (0 < $offset) {
                $dirname = \sprintf('\dirname(__DIR__, %d)', $offset + (int) $this->as_files);
            } elseif ($this->as_files) {
                $dirname = "\$container->targetDir.''";
                // empty string concatenation on purpose
            }
            if ($prefix || $suffix) {
                return \sprintf('(%s%s%s)', $prefix, $dirname, $suffix);
            }
            return $dirname;
        }
        return $this->do_export($value, true);
    }
    private function do_export(mixed $value, bool $resolve_env = false): mixed
    {
        $should_cache_value = $resolve_env && \is_string($value);
        if ($should_cache_value && isset($this->exported_variables[$value])) {
            return $this->exported_variables[$value];
        }
        if (\is_string($value) && str_contains($value, "\n")) {
            $clean_parts = explode("\n", $value);
            $clean_parts = array_map(static fn($part): string => var_export($part, true), $clean_parts);
            $export = implode('."\n".', $clean_parts);
        } else {
            $export = var_export($value, true);
        }
        if ($resolve_env && "'" === $export[0] && $export !== $resolved_export = $this->container->resolve_env_placeholders($export, "'.\$container->getEnv('string:%s').'")) {
            $export = $resolved_export;
            if (str_ends_with((string) $export, ".''")) {
                $export = substr((string) $export, 0, -3);
                if ("'" === $export[1]) {
                    $export = substr_replace($export, '', 23, 7);
                }
            }
            if ("'" === $export[1]) {
                $export = substr((string) $export, 3);
            }
        }
        if ($should_cache_value) {
            $this->exported_variables[$value] = $export;
        }
        return $export;
    }
    private function get_autoload_file(): ?string
    {
        $file = null;
        foreach (spl_autoload_functions() as $autoloader) {
            if (!\is_array($autoloader)) {
                continue;
            }
            if ($autoloader[0] instanceof Debug_Class_Loader) {
                $autoloader = $autoloader[0]->get_class_loader();
            }
            if (!\is_array($autoloader)) {
                continue;
            }
            if (!$autoloader[0] instanceof Class_Loader) {
                continue;
            }
            if (!$autoloader[0]->find_file(self::class)) {
                continue;
            }
            foreach (get_declared_classes() as $class) {
                if (str_starts_with($class, 'ComposerAutoloaderInit') && $class::get_loader() === $autoloader[0]) {
                    $file = \dirname((new \ReflectionClass($class))->get_file_name(), 2) . '/autoload.php';
                    if (null !== $this->target_dir_regex && preg_match($this->target_dir_regex . 'A', $file)) {
                        return $file;
                    }
                }
            }
        }
        return $file;
    }
    private function get_classes(Definition $definition, string $id): array
    {
        $classes = [];
        while ($definition instanceof Definition) {
            foreach ($definition->get_tag($this->preload_tags[0]) as $tag) {
                if (!isset($tag['class'])) {
                    throw new InvalidArgumentException(\sprintf('Missing attribute "class" on tag "%s" for service "%s".', $this->preload_tags[0], $id));
                }
                $classes[] = trim($tag['class'], '\\');
            }
            if ($class = $definition->get_class()) {
                $classes[] = trim($class, '\\');
            }
            $factory = $definition->get_factory();
            if (\is_string($factory) && !str_starts_with($factory, '@=') && str_contains($factory, '::')) {
                $factory = explode('::', $factory);
            }
            if (!\is_array($factory)) {
                $definition = $factory;
                continue;
            }
            $definition = $factory[0] ?? null;
            if (\is_string($definition)) {
                $classes[] = trim((string) $factory[0], '\\');
            }
        }
        return $classes;
    }
    private function is_proxy_candidate(Definition $definition, ?bool &$as_ghost_object, string $id): ?Definition
    {
        $as_ghost_object = false;
        if (['Closure', 'fromCallable'] === $definition->get_factory()) {
            return null;
        }
        if (!$definition->is_lazy() || !$this->has_proxy_dumper) {
            return null;
        }
        return $this->get_proxy_dumper()->is_proxy_candidate($definition, $as_ghost_object, $id) ? $definition : null;
    }
    /**
     * Removes comments from a PHP source string.
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     */
    private static function strip_comments(string $source): string
    {
        if (!\function_exists('token_get_all')) {
            return $source;
        }
        $raw_chunk = '';
        $output = '';
        $tokens = token_get_all($source);
        $ignore_space = false;
        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];
            if (!isset($token[1]) || 'b"' === $token) {
                $raw_chunk .= $token;
            } elseif (\T_START_HEREDOC === $token[0]) {
                $output .= $raw_chunk . $token[1];
                do {
                    $token = $tokens[++$i];
                    $output .= isset($token[1]) && 'b"' !== $token ? $token[1] : $token;
                } while (\T_END_HEREDOC !== $token[0]);
                $raw_chunk = '';
            } elseif (\T_WHITESPACE === $token[0]) {
                if ($ignore_space) {
                    $ignore_space = false;
                    continue;
                }
                // replace multiple new lines with a single newline
                $raw_chunk .= preg_replace(['/\n{2,}/S'], "\n", $token[1]);
            } elseif (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                if (!\in_array($raw_chunk[\strlen($raw_chunk) - 1], [' ', "\n", "\r", "\t"], true)) {
                    $raw_chunk .= ' ';
                }
                $ignore_space = true;
            } else {
                $raw_chunk .= $token[1];
                // The PHP-open tag already has a new-line
                if (\T_OPEN_TAG === $token[0]) {
                    $ignore_space = true;
                } else {
                    $ignore_space = false;
                }
            }
        }
        $output .= $raw_chunk;
        unset($tokens, $raw_chunk);
        gc_mem_caches();
        return $output;
    }
}