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
namespace Symfony\Component\Error_Handler;

use Composer\Installed_Versions;
use Doctrine\Common\Persistence\Proxy as LegacyProxy;
use Doctrine\Persistence\Proxy;
use Mockery\Mock_Interface;
use Phake\I_Mock;
use Php_Unit\Framework\Mock_Object\Matcher\Stateless_Invocation;
use Php_Unit\Framework\Mock_Object\Mock_Object;
use Php_Unit\Framework\Mock_Object\Stub;
use Prophecy\Prophecy\Prophecy_Subject_Interface;
use Proxy_Manager\Proxy\Proxy_Interface;
use Psr\Log\Log_Level;
use Symfony\Component\Dependency_Injection\Argument\Lazy_Closure;
use Symfony\Component\Error_Handler\Internal\Tentative_Types;
use Symfony\Component\Var_Exporter\Lazy_Object_Interface;
/**
 * Autoloader checking if the class is really defined in the file found.
 *
 * The ClassLoader will wrap all registered autoloaders
 * and will throw an exception if a file is found but does
 * not declare the class.
 *
 * It can also patch classes to turn docblocks into actual return types.
 * This behavior is controlled by the SYMFONY_PATCH_TYPE_DECLARATIONS env var,
 * which is a url-encoded array with the follow parameters:
 *  - "force": any value enables deprecation notices - can be any of:
 *      - "phpdoc" to patch only docblock annotations
 *      - "2" to add all possible return types
 *      - "1" to add return types but only to tests/final/internal/private methods
 *  - "php": the target version of PHP - e.g. "7.1" doesn't generate "object" types
 *  - "deprecations": "1" to trigger a deprecation notice when a child class misses a
 *                    return type while the parent declares an "@return" annotation
 *
 * Note that patching doesn't care about any coding style so you'd better to run
 * php-cs-fixer after, with rules "phpdoc_trim_consecutive_blank_line_separation"
 * and "no_superfluous_phpdoc_tags" enabled typically.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
class Debug_Class_Loader
{
    private const SPECIAL_RETURN_TYPES = ['void' => 'void', 'null' => 'null', 'resource' => 'resource', 'boolean' => 'bool', 'true' => 'true', 'false' => 'false', 'integer' => 'int', 'array' => 'array', 'bool' => 'bool', 'callable' => 'callable', 'float' => 'float', 'int' => 'int', 'iterable' => 'iterable', 'object' => 'object', 'string' => 'string', 'non-empty-string' => 'string', 'self' => 'self', 'parent' => 'parent', 'mixed' => 'mixed', 'static' => 'static', '$this' => 'static', 'list' => 'array', 'non-empty-list' => 'array', 'class-string' => 'string', 'never' => 'never'];
    private const BUILTIN_RETURN_TYPES = ['void' => true, 'array' => true, 'false' => true, 'bool' => true, 'callable' => true, 'float' => true, 'int' => true, 'iterable' => true, 'object' => true, 'string' => true, 'self' => true, 'parent' => true, 'mixed' => true, 'static' => true, 'null' => true, 'true' => true, 'never' => true];
    private const MAGIC_METHODS = ['__isset' => 'bool', '__sleep' => 'array', '__toString' => 'string', '__debugInfo' => 'array', '__serialize' => 'array', '__set' => 'void', '__unset' => 'void', '__unserialize' => 'void', '__wakeup' => 'void'];
    /**
     * @var callable
     */
    private $class_loader;
    private readonly bool $is_finder;
    private array $loaded = [];
    private array $patch_types = [];
    private static int $case_check;
    private static array $checked_classes = [];
    private static array $final = [];
    private static array $final_methods = [];
    private static array $final_properties = [];
    private static array $final_constants = [];
    private static array $deprecated = [];
    private static array $internal = [];
    private static array $internal_methods = [];
    private static array $annotated_parameters = [];
    private static array $darwin_cache = ['/' => ['/', []]];
    private static array $method = [];
    private static array $return_types = [];
    private static array $method_traits = [];
    private static array $file_offsets = [];
    /**
     * @var array<string, string>|null Re-mapping configuration for vendor comparison. Maps namespace prefixes to the value to be used for comparison.
     */
    private static ?array $namespace_remappings = null;
    /**
     * @var array<string, true|string> Caches vendor comparison strings. Maps FQCNs to vendor prefixes; true = root namespace (matches every vendor).
     */
    private static array $vendor_prefix_cache = [];
    public function __construct(callable $class_loader)
    {
        $this->class_loader = $class_loader;
        $this->is_finder = \is_array($class_loader) && method_exists($class_loader[0], 'findFile');
        parse_str((string) $_ENV['SYMFONY_PATCH_TYPE_DECLARATIONS'] ?? $_SERVER['SYMFONY_PATCH_TYPE_DECLARATIONS'] ?? getenv('SYMFONY_PATCH_TYPE_DECLARATIONS') ?: '', $this->patch_types);
        $this->patch_types += ['force' => null, 'php' => \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION, 'deprecations' => true];
        if ('phpdoc' === $this->patch_types['force']) {
            $this->patch_types['force'] = 'docblock';
        }
        if (!isset(self::$case_check)) {
            $file = is_file(__FILE__) ? __FILE__ : rtrim(realpath('.'), \DIRECTORY_SEPARATOR);
            $i = strrpos($file, \DIRECTORY_SEPARATOR);
            $dir = substr($file, 0, 1 + $i);
            $file = substr($file, 1 + $i);
            $test = strtoupper($file) === $file ? strtolower($file) : strtoupper($file);
            $test = realpath($dir . $test);
            if (false === $test || false === $i) {
                // filesystem is case-sensitive
                self::$case_check = 0;
            } elseif (str_ends_with($test, $file)) {
                // filesystem is case-insensitive and realpath() normalizes the case of characters
                self::$case_check = 1;
            } elseif ('Darwin' === \PHP_OS_FAMILY) {
                // on MacOSX, HFS+ is case-insensitive but realpath() doesn't normalize the case of characters
                self::$case_check = 2;
            } else {
                // filesystem case checks failed, fallback to disabling them
                self::$case_check = 0;
            }
        }
    }
    public function get_class_loader(): callable
    {
        return $this->class_loader;
    }
    /**
     * Wraps all autoloaders.
     *
     * @param array<string, string>|null $deprecationsNamespacesMapping Overrides the vendor-boundary detection used to
     *                                                                  decide whether deprecation notices are emitted.
     *                                                                  Each key is a fully-qualified class name or
     *                                                                  namespace prefix of the class being loaded;
     *                                                                  the corresponding value is the vendor string it
     *                                                                  will be compared against instead of its natural
     *                                                                  first namespace segment.
     *                                                                  Pass null (default) to use the first namespace segment as vendor name.
     */
    public static function enable(): void
    {
        $deprecations_namespaces_mapping = 1 <= \func_num_args() ? func_get_arg(0) : null;
        // Ensures we don't hit https://bugs.php.net/42098
        class_exists(Error_Handler::class);
        class_exists(Log_Level::class);
        if (!\is_array($functions = spl_autoload_functions())) {
            return;
        }
        self::$namespace_remappings = $deprecations_namespaces_mapping;
        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }
        foreach ($functions as $function) {
            if (!\is_array($function) || !$function[0] instanceof self) {
                $function = [new static($function), 'loadClass'];
            }
            spl_autoload_register($function);
        }
    }
    /**
     * Disables the wrapping.
     */
    public static function disable(): void
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            return;
        }
        self::$namespace_remappings = null;
        self::$vendor_prefix_cache = [];
        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }
        foreach ($functions as $function) {
            if (\is_array($function) && $function[0] instanceof self) {
                $function = $function[0]->get_class_loader();
            }
            spl_autoload_register($function);
        }
    }
    public static function check_classes(): bool
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            return false;
        }
        $loader = null;
        foreach ($functions as $function) {
            if (\is_array($function) && $function[0] instanceof self) {
                $loader = $function[0];
                break;
            }
        }
        if (null === $loader) {
            return false;
        }
        static $offsets = ['get_declared_interfaces' => 0, 'get_declared_traits' => 0, 'get_declared_classes' => 0];
        foreach ($offsets as $get_symbols => $i) {
            $symbols = $get_symbols();
            for (; $i < \count($symbols); ++$i) {
                if (!is_subclass_of($symbols[$i], Mock_Object::class) && !is_subclass_of($symbols[$i], Stub::class) && !is_subclass_of($symbols[$i], Prophecy_Subject_Interface::class) && !is_subclass_of($symbols[$i], Proxy::class) && !is_subclass_of($symbols[$i], Proxy_Interface::class) && !is_subclass_of($symbols[$i], Lazy_Object_Interface::class) && !is_subclass_of($symbols[$i], Legacy_Proxy::class) && !is_subclass_of($symbols[$i], Mock_Interface::class) && !is_subclass_of($symbols[$i], I_Mock::class) && !(is_subclass_of($symbols[$i], Lazy_Closure::class) && str_contains($symbols[$i], "@anonymous\x00"))) {
                    $loader->check_class($symbols[$i]);
                }
            }
            $offsets[$get_symbols] = $i;
        }
        return true;
    }
    public function find_file(string $class): ?string
    {
        return $this->is_finder ? $this->class_loader[0]->find_file($class) ?: null : null;
    }
    /**
     * Loads the given class or interface.
     *
     * @throws \RuntimeException
     */
    public function load_class(string $class): void
    {
        $e = error_reporting(error_reporting() | \E_PARSE | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR);
        try {
            if ($this->is_finder && !isset($this->loaded[$class])) {
                $this->loaded[$class] = true;
                if (!$file = $this->class_loader[0]->find_file($class) ?: '') {
                    // no-op
                } elseif (\function_exists('opcache_is_script_cached') && @opcache_is_script_cached($file)) {
                    include $file;
                    return;
                } elseif (false === include $file) {
                    return;
                }
            } else {
                ($this->class_loader)($class);
                $file = '';
            }
        } finally {
            error_reporting($e);
        }
        $this->check_class($class, $file);
    }
    private function check_class(string $class, ?string $file = null): void
    {
        $exists = null === $file || class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
        if (null !== $file && $class && '\\' === $class[0]) {
            $class = substr($class, 1);
        }
        if ($exists) {
            if (isset(self::$checked_classes[$class])) {
                return;
            }
            self::$checked_classes[$class] = true;
            $refl = new \ReflectionClass($class);
            if (null === $file && $refl->is_internal()) {
                return;
            }
            $name = $refl->get_name();
            if ($name !== $class && 0 === strcasecmp($name, $class)) {
                throw new \RuntimeException(\sprintf('Case mismatch between loaded and declared class names: "%s" vs "%s".', $class, $name));
            }
            $deprecations = $this->check_annotations($refl, $name);
            foreach ($deprecations as $message) {
                @trigger_error($message, \E_USER_DEPRECATED);
            }
        }
        if (!$file) {
            return;
        }
        if (!$exists) {
            if (str_contains($class, '/')) {
                throw new \RuntimeException(\sprintf('Trying to autoload a class with an invalid name "%s". Be careful that the namespace separator is "\" in PHP, not "/".', $class));
            }
            throw new \RuntimeException(\sprintf('The autoloader expected class "%s" to be defined in file "%s". The file was found but the class was not in it, the class name or namespace probably has a typo.', $class, $file));
        }
        if (self::$case_check && $message = $this->check_case($refl, $file, $class)) {
            throw new \RuntimeException(\sprintf('Case mismatch between class and real file names: "%s" vs "%s" in "%s".', $message[0], $message[1], $message[2]));
        }
    }
    public function check_annotations(\ReflectionClass $refl, string $class): array
    {
        if (\Symfony\Bridge\Php_Unit\Legacy\Symfony_Tests_Listener_For_V7::class === $class || 'Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV6' === $class) {
            return [];
        }
        $deprecations = [];
        // $className is a human-readable name used in deprecation messages: for anonymous classes
        // (whose internal name contains "@anonymous\0" followed by a file path) it is replaced
        // by a display-friendly form such as "ParentClass@anonymous"; for named classes it equals $class.
        $class_name = str_contains($class, "@anonymous\x00") ? ((get_parent_class($class) ?: key(class_implements($class))) ?: 'class') . '@anonymous' : $class;
        $parent = get_parent_class($class) ?: null;
        self::$return_types[$class] = [];
        $class_is_template = false;
        // Detect annotations on the class
        if ($doc = $this->parse_php_doc($refl)) {
            $class_is_template = isset($doc['template']) || isset($doc['template-covariant']);
            foreach (['final', 'deprecated', 'internal'] as $annotation) {
                if (null !== $description = $doc[$annotation][0] ?? null) {
                    self::${$annotation}[$class] = '' !== $description ? ' ' . $description . (preg_match('/[.!]$/', (string) $description) ? '' : '.') : '.';
                }
            }
            if ($refl->is_interface() && isset($doc['method'])) {
                foreach ($doc['method'] as $name => [$static, $return_type, $signature, $description]) {
                    self::$method[$class][] = [$class, $static, $return_type, $name . $signature, $description];
                    if ('' !== $return_type) {
                        $this->set_return_type($return_type, $refl->name, $name, $refl->get_file_name(), $parent);
                    }
                }
            }
        }
        $parent_and_own_interfaces = $this->get_own_interfaces($class, $parent);
        if ($parent) {
            $parent_and_own_interfaces[$parent] = $parent;
            if (!isset(self::$checked_classes[$parent])) {
                $this->check_class($parent);
            }
            if (isset(self::$final[$parent])) {
                $deprecations[] = \sprintf('The "%s" class is considered final%s It may change without further notice as of its next major version. You should not extend it from "%s".', $parent, self::$final[$parent], $class_name);
            }
        }
        // Detect if the parent is annotated
        foreach ($parent_and_own_interfaces + class_uses($class, false) as $use) {
            if (!isset(self::$checked_classes[$use])) {
                $this->check_class($use);
            }
            if (isset(self::$deprecated[$use]) && !isset(self::$deprecated[$class]) && !$this->are_from_the_same_vendor($class, $use)) {
                $type = class_exists($class, false) ? 'class' : (interface_exists($class, false) ? 'interface' : 'trait');
                $verb = class_exists($use, false) || interface_exists($class, false) ? 'extends' : (interface_exists($use, false) ? 'implements' : 'uses');
                $deprecations[] = \sprintf('The "%s" %s %s "%s" that is deprecated%s', $class_name, $type, $verb, $use, self::$deprecated[$use]);
            }
            if (isset(self::$internal[$use]) && !$this->are_from_the_same_vendor($class, $use)) {
                $deprecations[] = \sprintf('The "%s" %s is considered internal%s It may change without further notice. You should not use it from "%s".', $use, class_exists($use, false) ? 'class' : (interface_exists($use, false) ? 'interface' : 'trait'), self::$internal[$use], $class_name);
            }
            if (isset(self::$method[$use])) {
                if ($refl->is_abstract()) {
                    if (isset(self::$method[$class])) {
                        self::$method[$class] = array_merge(self::$method[$class], self::$method[$use]);
                    } else {
                        self::$method[$class] = self::$method[$use];
                    }
                } elseif (!$refl->is_interface()) {
                    if ($this->are_from_the_same_vendor($class, $use) && str_starts_with($class_name, 'Symfony\\') && (!class_exists(Installed_Versions::class) || 'symfony/symfony' !== Installed_Versions::get_root_package()['name'])) {
                        // skip "same vendor" @method deprecations for Symfony\* classes unless symfony/symfony is being tested
                        continue;
                    }
                    $has_call = $refl->has_method('__call');
                    $has_static_call = $refl->has_method('__callStatic');
                    foreach (self::$method[$use] as [$interface, $static, $return_type, $name, $description]) {
                        if ($static ? $has_static_call : $has_call) {
                            continue;
                        }
                        $real_name = substr((string) $name, 0, strpos((string) $name, '('));
                        if (!$refl->has_method($real_name) || !($method_refl = $refl->get_method($real_name))->is_public() || $static && !$method_refl->is_static() || !$static && $method_refl->is_static()) {
                            $deprecations[] = \sprintf('Class "%s" should implement method "%s::%s%s"%s', $class_name, ($static ? 'static ' : '') . $interface, $name, $return_type ? ': ' . $return_type : '', null === $description ? '.' : ': ' . $description);
                        }
                    }
                }
            }
        }
        if (trait_exists($class)) {
            $file = $refl->get_file_name();
            foreach ($refl->get_methods() as $method) {
                if ($method->get_file_name() === $file) {
                    self::$method_traits[$file][$method->get_start_line()] = $class;
                }
            }
            return $deprecations;
        }
        // Inherit @final, @internal, @param and @return annotations for methods
        self::$final_methods[$class] = [];
        self::$internal_methods[$class] = [];
        self::$annotated_parameters[$class] = [];
        self::$final_properties[$class] = [];
        self::$final_constants[$class] = [];
        foreach ($parent_and_own_interfaces as $use) {
            foreach (['finalMethods', 'internalMethods', 'annotatedParameters', 'returnTypes', 'finalProperties', 'finalConstants'] as $property) {
                if (isset(self::${$property}[$use])) {
                    self::${$property}[$class] = self::${$property}[$class] ? self::${$property}[$use] + self::${$property}[$class] : self::${$property}[$use];
                }
            }
            if (null !== (Tentative_Types::RETURN_TYPES[$use] ?? null)) {
                foreach (Tentative_Types::RETURN_TYPES[$use] as $method => $return_type) {
                    $return_type = explode('|', $return_type);
                    foreach ($return_type as $i => $t) {
                        if ('?' !== $t && !isset(self::BUILTIN_RETURN_TYPES[$t])) {
                            $return_type[$i] = '\\' . $t;
                        }
                    }
                    $return_type = implode('|', $return_type);
                    self::$return_types[$class] += [$method => [$return_type, str_starts_with($return_type, '?') ? substr($return_type, 1) . '|null' : $return_type, $use, '']];
                }
            }
        }
        foreach ($refl->get_methods() as $method) {
            if ($method->class !== $class) {
                continue;
            }
            // If this method was introduced via a trait, use the trait's vendor for checks
            // rather than the containing class' vendor.
            $trait_class = self::$method_traits[$method->get_file_name()][$method->get_start_line()] ?? null;
            if ($parent && isset(self::$final_methods[$parent][$method->name])) {
                [$declaring_class, $message] = self::$final_methods[$parent][$method->name];
                $deprecations[] = \sprintf('The "%s::%s()" method is considered final%s It may change without further notice as of its next major version. You should not extend it from "%s".', $declaring_class, $method->name, $message, $class_name);
            }
            if (isset(self::$internal_methods[$class][$method->name])) {
                [$declaring_class, $message] = self::$internal_methods[$class][$method->name];
                if (!$this->are_from_the_same_vendor($trait_class ?? $class, $declaring_class)) {
                    $deprecations[] = \sprintf('The "%s::%s()" method is considered internal%s It may change without further notice. You should not extend it from "%s".', $declaring_class, $method->name, $message, $class_name);
                }
            }
            // To read method annotations
            $doc = $this->parse_php_doc($method);
            if (($class_is_template || isset($doc['template']) || isset($doc['template-covariant'])) && $method->has_return_type()) {
                unset($doc['return']);
            }
            if (isset(self::$annotated_parameters[$class][$method->name])) {
                $defined_parameters = [];
                foreach ($method->get_parameters() as $parameter) {
                    $defined_parameters[$parameter->name] = true;
                }
                foreach (self::$annotated_parameters[$class][$method->name] as $parameter_name => $deprecation) {
                    if (!isset($defined_parameters[$parameter_name]) && !isset($doc['param'][$parameter_name])) {
                        $deprecations[] = \sprintf($deprecation, $class_name);
                    }
                }
            }
            $force_patch_types = $this->patch_types['force'];
            if ($can_add_return_type = null !== $force_patch_types && !str_contains($method->get_file_name(), \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR)) {
                $this->patch_types['force'] = $force_patch_types ?: 'docblock';
                $can_add_return_type = 2 === (int) $force_patch_types || false !== stripos($method->get_file_name(), \DIRECTORY_SEPARATOR . 'Tests' . \DIRECTORY_SEPARATOR) || $refl->is_final() || $method->is_final() || $method->is_private() || '.' === (self::$internal[$class] ?? null) && !$refl->is_abstract() || '.' === (self::$final[$class] ?? null) || '' === ($doc['final'][0] ?? null) || '' === ($doc['internal'][0] ?? null);
            }
            if (null !== ($return_type = self::$return_types[$class][$method->name] ?? null) && 'docblock' === $this->patch_types['force'] && !$method->has_return_type() && isset(Tentative_Types::RETURN_TYPES[$return_type[2]][$method->name])) {
                $this->patch_return_type_will_change($method);
            }
            if (null !== ($return_type ??= self::MAGIC_METHODS[$method->name] ?? null) && !$method->has_return_type() && !isset($doc['return'])) {
                [$normalized_type, $return_type, $declaring_class, $declaring_file] = \is_string($return_type) ? [$return_type, $return_type, '', ''] : $return_type;
                if ($can_add_return_type && 'docblock' !== $this->patch_types['force']) {
                    $this->patch_method($method, $return_type, $declaring_file, $normalized_type);
                }
                if (!isset($doc['deprecated']) && !$this->are_from_the_same_vendor($trait_class ?? $class, $declaring_class)) {
                    if ('docblock' === $this->patch_types['force']) {
                        $this->patch_method($method, $return_type, $declaring_file, $normalized_type);
                    } elseif ('' !== $declaring_class && $this->patch_types['deprecations']) {
                        $deprecations[] = \sprintf('Method "%s::%s()" might add "%s" as a native return type declaration in the future. Do the same in %s "%s" now to avoid errors or add an explicit @return annotation to suppress this message.', $declaring_class, $method->name, $normalized_type, interface_exists($declaring_class) ? 'implementation' : 'child class', $class_name);
                    }
                }
            }
            if (!$doc) {
                $this->patch_types['force'] = $force_patch_types;
                continue;
            }
            if (isset($doc['return'])) {
                $this->set_return_type($doc['return'] ?? self::MAGIC_METHODS[$method->name], $method->class, $method->name, $method->get_file_name(), $parent, $method->get_return_type());
                if (isset(self::$return_types[$class][$method->name][0]) && $can_add_return_type) {
                    $this->fix_return_statements($method, self::$return_types[$class][$method->name][0]);
                }
                if ($method->is_private()) {
                    unset(self::$return_types[$class][$method->name]);
                }
            }
            $this->patch_types['force'] = $force_patch_types;
            if ($method->is_private()) {
                continue;
            }
            $final_or_internal = false;
            foreach (['final', 'internal'] as $annotation) {
                if (null !== $description = $doc[$annotation][0] ?? null) {
                    self::${$annotation . 'Methods'}[$class][$method->name] = [$class, '' !== $description ? ' ' . $description . (preg_match('/[[:punct:]]$/', (string) $description) ? '' : '.') : '.'];
                    $final_or_internal = true;
                }
            }
            if ($final_or_internal) {
                continue;
            }
            if ($method->is_constructor()) {
                continue;
            }
            if (!isset($doc['param'])) {
                continue;
            }
            if (Stateless_Invocation::class === $class) {
                continue;
            }
            if (!isset(self::$annotated_parameters[$class][$method->name])) {
                $defined_parameters = [];
                foreach ($method->get_parameters() as $parameter) {
                    $defined_parameters[$parameter->name] = true;
                }
            }
            foreach ($doc['param'] as $parameter_name => $parameter_type) {
                if (!isset($defined_parameters[$parameter_name])) {
                    self::$annotated_parameters[$class][$method->name][$parameter_name] = \sprintf('The "%%s::%s()" method will require a new "%s$%s" argument in the next major version of its %s "%s", not defining it is deprecated.', $method->name, $parameter_type ? $parameter_type . ' ' : '', $parameter_name, interface_exists($class_name) ? 'interface' : 'parent class', $class_name);
                }
            }
        }
        $finals = isset(self::$final[$class]) || $refl->is_final() ? [] : ['finalConstants' => $refl->get_reflection_constants(\Reflection_Class_Constant::IS_PUBLIC | \Reflection_Class_Constant::IS_PROTECTED), 'finalProperties' => $refl->get_properties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED)];
        foreach ($finals as $type => $reflectors) {
            foreach ($reflectors as $r) {
                if ($r->class !== $class) {
                    continue;
                }
                $doc = $this->parse_php_doc($r);
                foreach ($parent_and_own_interfaces as $use) {
                    if (isset(self::${$type}[$use][$r->name]) && !isset($doc['deprecated']) && ('finalConstants' === $type || substr($use, 0, strrpos($use, '\\')) !== substr($use, 0, strrpos($class, '\\')))) {
                        $msg = 'finalConstants' === $type ? '%s" constant' : '$%s" property';
                        $deprecations[] = \sprintf('The "%s::' . $msg . ' is considered final. You should not override it in "%s".', self::${$type}[$use][$r->name], $r->name, $class);
                    }
                }
                if (isset($doc['final']) || 'finalProperties' === $type && str_starts_with($class, 'Symfony\\') && !$r->has_type()) {
                    self::${$type}[$class][$r->name] = $class;
                }
            }
        }
        return $deprecations;
    }
    public function check_case(\ReflectionClass $refl, string $file, string $class): ?array
    {
        $real = explode('\\', $class . strrchr($file, '.'));
        $tail = explode(\DIRECTORY_SEPARATOR, str_replace('/', \DIRECTORY_SEPARATOR, $file));
        $i = \count($tail) - 1;
        $j = \count($real) - 1;
        while (isset($tail[$i], $real[$j]) && $tail[$i] === $real[$j]) {
            --$i;
            --$j;
        }
        array_splice($tail, 0, $i + 1);
        if (!$tail) {
            return null;
        }
        $tail = \DIRECTORY_SEPARATOR . implode(\DIRECTORY_SEPARATOR, $tail);
        $tail_len = \strlen($tail);
        $real = $refl->get_file_name();
        if (2 === self::$case_check) {
            $real = $this->darwin_realpath($real);
        }
        if (0 === substr_compare($real, $tail, -$tail_len, $tail_len, true) && 0 !== substr_compare($real, $tail, -$tail_len, $tail_len, false)) {
            return [substr($tail, -$tail_len + 1), substr($real, -$tail_len + 1), substr($real, 0, -$tail_len + 1)];
        }
        return null;
    }
    /**
     * `realpath` on MacOSX doesn't normalize the case of characters.
     */
    private function darwin_realpath(string $real): string
    {
        $i = 1 + strrpos($real, '/');
        $file = substr($real, $i);
        $real = substr($real, 0, $i);
        if (isset(self::$darwin_cache[$real])) {
            $k_dir = $real;
        } else {
            $k_dir = strtolower($real);
            if (isset(self::$darwin_cache[$k_dir])) {
                $real = self::$darwin_cache[$k_dir][0];
            } else {
                $dir = getcwd();
                if (!@chdir($real)) {
                    return $real . $file;
                }
                $real = getcwd() . '/';
                chdir($dir);
                $dir = $real;
                $k = $k_dir;
                $i = \strlen($dir) - 1;
                while (!isset(self::$darwin_cache[$k])) {
                    self::$darwin_cache[$k] = [$dir, []];
                    self::$darwin_cache[$dir] =& self::$darwin_cache[$k];
                    $k = substr($k, 0, ++$i);
                    $dir = substr($dir, 0, $i--);
                }
            }
        }
        $dir_files = self::$darwin_cache[$k_dir][1];
        if (!isset($dir_files[$file]) && str_ends_with($file, ') : eval()\'d code')) {
            // Get the file name from "file_name.php(123) : eval()'d code"
            $file = substr($file, 0, strrpos($file, '(', -17));
        }
        if (isset($dir_files[$file])) {
            return $real . $dir_files[$file];
        }
        $k_file = strtolower($file);
        if (!isset($dir_files[$k_file])) {
            foreach (scandir($real, 2) as $f) {
                if ('.' !== $f[0]) {
                    $dir_files[$f] = $f;
                    if ($f === $file) {
                        $k_file = $file;
                    } elseif ($f !== $k = strtolower($f)) {
                        $dir_files[$k] = $f;
                    }
                }
            }
            self::$darwin_cache[$k_dir][1] = $dir_files;
        }
        return $real . $dir_files[$k_file];
    }
    /**
     * `class_implements` includes interfaces from the parents so we have to manually exclude them.
     *
     * @return string[]
     */
    private function get_own_interfaces(string $class, ?string $parent): array
    {
        $own_interfaces = class_implements($class, false);
        if ($parent) {
            foreach (class_implements($parent, false) as $interface) {
                unset($own_interfaces[$interface]);
            }
        }
        foreach ($own_interfaces as $interface) {
            foreach (class_implements($interface) as $interface) {
                unset($own_interfaces[$interface]);
            }
        }
        return $own_interfaces;
    }
    /**
     * @param string $class The class being loaded and inspected for deprecation violations
     * @param string $use   The parent class, interface, or trait it extends, implements, or uses
     */
    private function are_from_the_same_vendor(string $class, string $use): bool
    {
        $vendor = self::$vendor_prefix_cache[$class] ?? $this->get_vendor_entry($class);
        return true === $vendor || $vendor === (self::$vendor_prefix_cache[$use] ?? $this->get_vendor_entry($use));
    }
    /**
     * Returns the vendor string for a class, computing and caching it if necessary. Takes
     * remapping into account, see {@see enable()}.
     *
     * @return true|string the vendor prefix to consider; true when the class is in the root namespace
     *                     (matches every vendor)
     */
    private function get_vendor_entry(string $class): bool|string
    {
        if (isset(self::$vendor_prefix_cache[$class])) {
            return self::$vendor_prefix_cache[$class];
        }
        // Anonymous classes carry a file path in their internal name instead of a namespace,
        // so the vendor prefix must be derived from the namespace declared in their source file.
        // Named classes use the class name itself as the lookup key.
        if (str_contains($class, "@anonymous\x00")) {
            $refl = new \ReflectionClass($class);
            $lookup_key = $refl->get_file_name() && preg_match('/^namespace ([^;\\\\\\s]++)[;\\\\]/m', @file_get_contents($refl->get_file_name()) ?: '', $m) ? $m[1] : '';
        } else {
            $lookup_key = $class;
        }
        if (\is_array(self::$namespace_remappings)) {
            // Find longest namespace prefix for which a mapping exists
            $mapped_namespace = $lookup_key;
            while (!isset(self::$namespace_remappings[$mapped_namespace]) && false !== $pos = strrpos($mapped_namespace, '\\')) {
                $mapped_namespace = substr($mapped_namespace, 0, $pos);
            }
            if (isset(self::$namespace_remappings[$mapped_namespace])) {
                return self::$vendor_prefix_cache[$class] = self::$namespace_remappings[$mapped_namespace];
            }
        }
        $sep = strpos($lookup_key, '\\') ?: strpos($lookup_key, '_');
        if (!$sep) {
            // The class is in the root namespace: it matches every vendor.
            return self::$vendor_prefix_cache[$class] = true;
        }
        return self::$vendor_prefix_cache[$class] = substr($lookup_key, 0, $sep);
    }
    private function set_return_type(string $types, string $class, string $method, string $filename, ?string $parent, ?\Reflection_Type $return_type = null): void
    {
        if ('__construct' === $method) {
            return;
        }
        if ('null' === $types) {
            self::$return_types[$class][$method] = ['null', 'null', $class, $filename];
            return;
        }
        if ($nullable = str_starts_with($types, 'null|')) {
            $types = substr($types, 5);
        } elseif ($nullable = str_ends_with($types, '|null')) {
            $types = substr($types, 0, -5);
        }
        $array_type = ['array' => 'array'];
        $types_map = [];
        $glue = str_contains($types, '&') ? '&' : '|';
        foreach (explode($glue, $types) as $t) {
            $t = self::SPECIAL_RETURN_TYPES[strtolower($t)] ?? $t;
            $types_map[$this->normalize_type($t, $class, $parent, $return_type)][$t] = $t;
        }
        if (isset($types_map['array'])) {
            if (isset($types_map['Traversable']) || isset($types_map['\Traversable'])) {
                $types_map['iterable'] = $array_type !== $types_map['array'] ? $types_map['array'] : ['iterable'];
                unset($types_map['array'], $types_map['Traversable'], $types_map['\Traversable']);
            } elseif ($array_type !== $types_map['array'] && isset(self::$return_types[$class][$method]) && !$return_type) {
                return;
            }
        }
        if (isset($types_map['array']) && isset($types_map['iterable'])) {
            if ($array_type !== $types_map['array']) {
                $types_map['iterable'] = $types_map['array'];
            }
            unset($types_map['array']);
        }
        $iterable = $object = true;
        foreach ($types_map as $n => $t) {
            if ('null' !== $n) {
                $iterable = $iterable && (\in_array($n, ['array', 'iterable'], true) || str_contains($n, 'Iterator'));
                $object = $object && (\in_array($n, ['callable', 'object', '$this', 'static'], true) || !isset(self::SPECIAL_RETURN_TYPES[$n]));
            }
        }
        $php_types = [];
        $doc_types = [];
        foreach ($types_map as $n => $t) {
            if (str_contains($n, '::')) {
                [$defining_class, $constant_name] = explode('::', $n, 2);
                $defining_class = match ($defining_class) {
                    'self', 'static', 'parent' => $class,
                    default => $defining_class,
                };
                if (!\defined($defining_class . '::' . $constant_name)) {
                    return;
                }
                $constant = new \Reflection_Class_Constant($defining_class, $constant_name);
                if ($constant_type = $constant->get_type()) {
                    if ($constant_type instanceof \ReflectionNamedType) {
                        $n = $constant_type->get_name();
                    } else {
                        return;
                    }
                } else {
                    $n = \gettype($constant->get_value());
                }
            }
            if ('null' === $n) {
                $nullable = true;
                continue;
            }
            $doc_types[] = $t;
            if ('mixed' === $n || 'void' === $n) {
                $nullable = false;
                $php_types = ['' => $n];
                continue;
            }
            if ('resource' === $n) {
                // there is no native type for "resource"
                return;
            }
            if (!preg_match('/^(?:\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)+$/', $n)) {
                // exclude any invalid PHP class name (e.g. `Cookie::SAMESITE_*`)
                continue;
            }
            if (!isset($php_types['']) && !\in_array($n, $php_types, true)) {
                $php_types[] = $n;
            }
        }
        $doc_types = array_merge([], ...$doc_types);
        if (!$php_types) {
            return;
        }
        if (1 < \count($php_types)) {
            if ($iterable && '8.0' > $this->patch_types['php']) {
                $php_types = $doc_types = ['iterable'];
            } elseif ($object && 'object' === $this->patch_types['force']) {
                $php_types = $doc_types = ['object'];
            } elseif ('8.0' > $this->patch_types['php']) {
                // ignore multi-types return declarations
                return;
            }
        }
        $php_type = \sprintf($nullable ? 1 < \count($php_types) ? '%s|null' : '?%s' : '%s', implode($glue, $php_types));
        $doc_type = \sprintf($nullable ? '%s|null' : '%s', implode($glue, $doc_types));
        self::$return_types[$class][$method] = [$php_type, $doc_type, $class, $filename];
    }
    private function normalize_type(string $type, string $class, ?string $parent, ?\Reflection_Type $return_type): string
    {
        if (isset(self::SPECIAL_RETURN_TYPES[$lc_type = strtolower($type)])) {
            if ('parent' === $lc_type = self::SPECIAL_RETURN_TYPES[$lc_type]) {
                $lc_type = null !== $parent ? '\\' . $parent : 'parent';
            } elseif ('self' === $lc_type) {
                $lc_type = '\\' . $class;
            }
            return $lc_type;
        }
        // We could resolve "use" statements to return the FQDN
        // but this would be too expensive for a runtime checker
        if (!str_ends_with($type, '[]')) {
            return $type;
        }
        if ($return_type instanceof \ReflectionNamedType) {
            $type = $return_type->get_name();
            if ('mixed' !== $type) {
                return isset(self::SPECIAL_RETURN_TYPES[$type]) ? $type : '\\' . $type;
            }
        }
        return 'array';
    }
    /**
     * Utility method to add #[ReturnTypeWillChange] where php triggers deprecations.
     */
    private function patch_return_type_will_change(\ReflectionMethod $method): void
    {
        if (\count($method->get_attributes(\Return_Type_Will_Change::class))) {
            return;
        }
        if (!is_file($file = $method->get_file_name())) {
            return;
        }
        $file_offset = self::$file_offsets[$file] ?? 0;
        $code = file($file);
        $start_line = $method->get_start_line() + $file_offset - 2;
        if (false !== stripos($code[$start_line], 'ReturnTypeWillChange')) {
            return;
        }
        $code[$start_line] .= "    #[\\ReturnTypeWillChange]\n";
        self::$file_offsets[$file] = 1 + $file_offset;
        file_put_contents($file, $code);
    }
    /**
     * Utility method to add @return annotations to the Symfony code-base where it triggers self-deprecations.
     */
    private function patch_method(\ReflectionMethod $method, string $return_type, string $declaring_file, string $normalized_type): void
    {
        static $patched_methods = [];
        static $use_statements = [];
        if (!is_file($file = $method->get_file_name()) || isset($patched_methods[$file][$start_line = $method->get_start_line()])) {
            return;
        }
        $patched_methods[$file][$start_line] = true;
        $file_offset = self::$file_offsets[$file] ?? 0;
        $start_line += $file_offset - 2;
        if ($nullable = str_ends_with($return_type, '|null')) {
            $return_type = substr($return_type, 0, -5);
        }
        $glue = str_contains($return_type, '&') ? '&' : '|';
        $return_type = explode($glue, $return_type);
        $code = file($file);
        foreach ($return_type as $i => $type) {
            if (preg_match('/((?:\[\])+)$/', $type, $m)) {
                $type = substr($type, 0, -\strlen($m[1]));
                $format = '%s' . $m[1];
            } else {
                $format = null;
            }
            if (isset(self::SPECIAL_RETURN_TYPES[$type])) {
                continue;
            }
            if ('\\' === $type[0] && !$p = strrpos($type, '\\', 1)) {
                continue;
            }
            [$namespace, $use_offset, $use_map] = $use_statements[$file] ??= self::get_use_statements($file);
            if ('\\' !== $type[0]) {
                [$declaring_namespace, , $declaring_use_map] = $use_statements[$declaring_file] ??= self::get_use_statements($declaring_file);
                $p = strpos($type, '\\', 1);
                $alias = $p ? substr($type, 0, $p) : $type;
                if (isset($declaring_use_map[$alias])) {
                    $type = '\\' . $declaring_use_map[$alias] . ($p ? substr($type, $p) : '');
                } else {
                    $type = '\\' . $declaring_namespace . $type;
                }
                $p = strrpos($type, '\\', 1);
            }
            $alias = substr($type, 1 + $p);
            $type = substr($type, 1);
            if (!isset($use_map[$alias]) && (class_exists($c = $namespace . $alias) || interface_exists($c) || trait_exists($c))) {
                $use_map[$alias] = $c;
            }
            if (!isset($use_map[$alias])) {
                $use_statements[$file][2][$alias] = $type;
                $code[$use_offset] = "use {$type};\n" . $code[$use_offset];
                ++$file_offset;
            } elseif ($use_map[$alias] !== $type) {
                $alias .= 'FIXME';
                $use_statements[$file][2][$alias] = $type;
                $code[$use_offset] = "use {$type} as {$alias};\n" . $code[$use_offset];
                ++$file_offset;
            }
            $return_type[$i] = null !== $format ? \sprintf($format, $alias) : $alias;
        }
        if ('docblock' === $this->patch_types['force'] || 'object' === $normalized_type && '7.1' === $this->patch_types['php']) {
            $return_type = implode($glue, $return_type) . ($nullable ? '|null' : '');
            if (str_contains($code[$start_line], '#[')) {
                --$start_line;
            }
            if ($method->get_doc_comment()) {
                $code[$start_line] = "     * @return {$return_type}\n" . $code[$start_line];
            } else {
                $code[$start_line] .= <<<EOTXT
                    /**
                     * @return {$return_type}
                     */
                
                EOTXT;
            }
            $file_offset += substr_count($code[$start_line], "\n") - 1;
        }
        self::$file_offsets[$file] = $file_offset;
        file_put_contents($file, $code);
        $this->fix_return_statements($method, $normalized_type);
    }
    private static function get_use_statements(string $file): array
    {
        $namespace = '';
        $use_map = [];
        $use_offset = 0;
        if (!is_file($file)) {
            return [$namespace, $use_offset, $use_map];
        }
        $file = file($file);
        for ($i = 0; $i < \count($file); ++$i) {
            if (preg_match('/^(class|interface|trait|abstract) /', $file[$i])) {
                break;
            }
            if (str_starts_with($file[$i], 'namespace ')) {
                $namespace = substr($file[$i], \strlen('namespace '), -2) . '\\';
                $use_offset = $i + 2;
            }
            if (str_starts_with($file[$i], 'use ')) {
                $use_offset = $i;
                for (; str_starts_with($file[$i], 'use '); ++$i) {
                    $u = explode(' as ', substr($file[$i], 4, -2), 2);
                    if (1 === \count($u)) {
                        $p = strrpos($u[0], '\\');
                        $use_map[substr($u[0], false !== $p ? 1 + $p : 0)] = $u[0];
                    } else {
                        $use_map[$u[1]] = $u[0];
                    }
                }
                break;
            }
        }
        return [$namespace, $use_offset, $use_map];
    }
    private function fix_return_statements(\ReflectionMethod $method, string $return_type): void
    {
        if ('docblock' !== $this->patch_types['force']) {
            if ('7.1' === $this->patch_types['php'] && 'object' === ltrim($return_type, '?')) {
                return;
            }
            if ('7.4' > $this->patch_types['php'] && $method->has_return_type()) {
                return;
            }
            if ('8.0' > $this->patch_types['php'] && (str_contains($return_type, '|') || \in_array($return_type, ['mixed', 'static'], true))) {
                return;
            }
            if ('8.1' > $this->patch_types['php'] && str_contains($return_type, '&')) {
                return;
            }
        }
        if (!is_file($file = $method->get_file_name())) {
            return;
        }
        $fixed_code = $code = file($file);
        $i = (self::$file_offsets[$file] ?? 0) + $method->get_start_line();
        if ('?' !== $return_type && 'docblock' !== $this->patch_types['force']) {
            $fixed_code[$i - 1] = preg_replace('/\)(?::[^;\n]++)?(;?\n)/', "): {$return_type}\\1", $code[$i - 1]);
        }
        $end = $method->is_generator() ? $i : $method->get_end_line();
        $in_closure = false;
        $braces = 0;
        for (; $i < $end; ++$i) {
            if (!$in_closure) {
                $in_closure = str_contains($code[$i], 'function (');
            }
            if ($in_closure) {
                $braces += substr_count($code[$i], '{') - substr_count($code[$i], '}');
                $in_closure = $braces > 0;
                continue;
            }
            if ('void' === $return_type) {
                $fixed_code[$i] = str_replace('    return null;', '    return;', $code[$i]);
            } elseif ('mixed' === $return_type || '?' === $return_type[0]) {
                $fixed_code[$i] = str_replace('    return;', '    return null;', $code[$i]);
            } else {
                $fixed_code[$i] = str_replace('    return;', "    return {$return_type}!?;", $code[$i]);
            }
        }
        if ($fixed_code !== $code) {
            file_put_contents($file, $fixed_code);
        }
    }
    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector
     */
    private function parse_php_doc(\Reflector $reflector): array
    {
        if (!$doc = $reflector->get_doc_comment()) {
            return [];
        }
        $tag_name = '';
        $tag_content = '';
        $tags = [];
        foreach (explode("\n", substr($doc, 3, -2)) as $line) {
            $line = ltrim($line);
            $line = ltrim($line, '*');
            if ('' === $line = trim($line)) {
                if ('' !== $tag_name) {
                    $tags[$tag_name][] = $tag_content;
                }
                $tag_name = $tag_content = '';
                continue;
            }
            if ('@' === $line[0]) {
                if ('' !== $tag_name) {
                    $tags[$tag_name][] = $tag_content;
                    $tag_content = '';
                }
                if (preg_match('{^@([-a-zA-Z0-9_:]++)(\s|$)}', $line, $m)) {
                    $tag_name = $m[1];
                    $tag_content = str_replace("\t", ' ', ltrim(substr($line, 2 + \strlen($tag_name))));
                } else {
                    $tag_name = '';
                }
            } elseif ('' !== $tag_name) {
                $tag_content .= ' ' . str_replace("\t", ' ', $line);
            }
        }
        if ('' !== $tag_name) {
            $tags[$tag_name][] = $tag_content;
        }
        foreach ($tags['method'] ?? [] as $i => $method) {
            unset($tags['method'][$i]);
            $parts = preg_split('{(\s++|\((?:[^()]*+|(?R))*\)(?: *: *[^ ]++)?|<(?:[^<>]*+|(?R))*>|\{(?:[^{}]*+|(?R))*\})}', $method, -1, \PREG_SPLIT_DELIM_CAPTURE);
            $return_type = '';
            $static = 'static' === $parts[0];
            for ($i = $static ? 2 : 0; null !== $p = $parts[$i] ?? null; $i += 2) {
                if (\in_array($p, ['', 'callable'], true) || \in_array(substr($return_type, -1), ['|', '&'], true) || \in_array($p[0], ['|', '&'], true)) {
                    $return_type .= trim($parts[$i - 1] ?? '') . $p;
                    continue;
                }
                $signature = '(' === ($parts[$i + 1][0] ?? '(') ? $parts[$i + 1] ?? '()' : null;
                if (null === $signature && '' === $return_type) {
                    $return_type = $p;
                    continue;
                }
                if ($static && 2 === $i) {
                    $static = false;
                    $return_type = 'static';
                }
                if (\in_array($description = trim(implode('', \array_slice($parts, 2 + $i))), ['', '.'], true)) {
                    $description = null;
                } elseif (!preg_match('/[.!]$/', $description)) {
                    $description .= '.';
                }
                $tags['method'][$p] = [$static, $return_type, $signature ?? '()', $description];
                break;
            }
        }
        foreach ($tags['param'] ?? [] as $i => $param) {
            unset($tags['param'][$i]);
            if (\strlen($param) !== strcspn($param, '<{(')) {
                $param = preg_replace('{\(([^()]*+|(?R))*\)(?: *: *[^ ]++)?|<([^<>]*+|(?R))*>|\{([^{}]*+|(?R))*\}}', '', $param);
            }
            if (false === $i = strpos($param, '$')) {
                continue;
            }
            $type = 0 === $i ? '' : rtrim(substr($param, 0, $i), ' &');
            $param = substr($param, 1 + $i, (strpos($param, ' ', $i) ?: 1 + $i + \strlen($param)) - $i - 1);
            $tags['param'][$param] = $type;
        }
        foreach (['var', 'return'] as $k) {
            if (null === $v = $tags[$k][0] ?? null) {
                continue;
            }
            if (\strlen($v) !== strcspn($v, '<{(')) {
                $v = preg_replace('{\(([^()]*+|(?R))*\)(?: *: *[^ ]++)?|<([^<>]*+|(?R))*>|\{([^{}]*+|(?R))*\}}', '', $v);
            }
            $tags[$k] = substr($v, 0, strpos($v, ' ') ?: \strlen($v)) ?: null;
        }
        return $tags;
    }
}