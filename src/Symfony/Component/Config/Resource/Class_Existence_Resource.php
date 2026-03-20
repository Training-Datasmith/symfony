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
namespace Symfony\Component\Config\Resource;

/**
 * ClassExistenceResource represents a class existence.
 * Freshness is only evaluated against resource existence.
 *
 * The resource must be a fully-qualified class name.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Class_Existence_Resource implements Self_Checking_Resource_Interface
{
    private ?array $exists = null;
    private static int $autoload_level = 0;
    private static ?string $autoloaded_class = null;
    private static array $exists_cache = [];
    /**
     * @param string    $resource The fully-qualified class name
     * @param bool|null $exists   Boolean when the existence check has already been done
     */
    public function __construct(private string $resource, ?bool $exists = null)
    {
        if (null !== $exists) {
            $this->exists = [$exists, null];
        }
    }
    public function __toString(): string
    {
        return $this->resource;
    }
    public function get_resource(): string
    {
        return $this->resource;
    }
    /**
     * @throws \ReflectionException when a parent class/interface/trait is not found
     */
    public function is_fresh(int $timestamp): bool
    {
        $loaded = class_exists($this->resource, false) || interface_exists($this->resource, false) || trait_exists($this->resource, false);
        if (null !== $exists =& self::$exists_cache[$this->resource]) {
            if ($loaded) {
                $exists = [true, null];
            } elseif (0 >= $timestamp && !$exists[0] && null !== $exists[1]) {
                throw new \Reflection_Exception($exists[1]);
            }
        } elseif ([false, null] === $exists = [$loaded, null]) {
            if (!self::$autoload_level++) {
                spl_autoload_register(self::class . '::throwOnRequiredClass');
            }
            $autoloaded_class = self::$autoloaded_class;
            self::$autoloaded_class = ltrim($this->resource, '\\');
            try {
                $exists[0] = class_exists($this->resource) || interface_exists($this->resource, false) || trait_exists($this->resource, false);
            } catch (\Exception $e) {
                $exists[1] = $e->get_message();
                try {
                    self::throw_on_required_class($this->resource, $e);
                } catch (\Reflection_Exception $e) {
                    if (0 >= $timestamp) {
                        throw $e;
                    }
                }
            } catch (\Throwable $e) {
                $exists[1] = $e->get_message();
                throw $e;
            } finally {
                self::$autoloaded_class = $autoloaded_class;
                if (!--self::$autoload_level) {
                    spl_autoload_unregister(self::class . '::throwOnRequiredClass');
                }
            }
        }
        $this->exists ??= $exists;
        return $this->exists[0] xor !$exists[0];
    }
    public function __serialize(): array
    {
        if (null === $this->exists) {
            $this->is_fresh(0);
        }
        return ['resource' => $this->resource, 'exists' => $this->exists];
    }
    public function __unserialize(array $data): void
    {
        $this->resource = array_shift($data);
        $this->exists = array_shift($data);
        if (\is_bool($this->exists)) {
            $this->exists = [$this->exists, null];
        }
    }
    /**
     * Throws a reflection exception when the passed class does not exist but is required.
     *
     * A class is considered "not required" when it's loaded as part of a "class_exists" or similar check.
     *
     * This function can be used as an autoload function to throw a reflection
     * exception if the class was not found by previous autoload functions.
     *
     * A previous exception can be passed. In this case, the class is considered as being
     * required totally, so if it doesn't exist, a reflection exception is always thrown.
     * If it exists, the previous exception is rethrown.
     *
     * @throws \ReflectionException
     *
     * @internal
     */
    public static function throw_on_required_class(string $class, ?\Exception $previous = null): void
    {
        // If the passed class is the resource being checked, we shouldn't throw.
        if (null === $previous && self::$autoloaded_class === $class) {
            return;
        }
        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            if (null !== $previous) {
                throw $previous;
            }
            return;
        }
        if ($previous instanceof \Reflection_Exception) {
            throw $previous;
        }
        $message = \sprintf('Class "%s" not found.', $class);
        if ($class !== (self::$autoloaded_class ?? $class)) {
            $message = substr_replace($message, \sprintf(' while loading "%s"', self::$autoloaded_class), -1, 0);
        }
        if (null !== $previous) {
            $message = $previous->get_message();
        }
        $e = new \Reflection_Exception($message, 0, $previous);
        if (null !== $previous) {
            throw $e;
        }
        $trace = debug_backtrace();
        $autoload_frame = ['function' => 'spl_autoload_call', 'args' => [$class]];
        if (isset($trace[1])) {
            $caller_frame = $trace[1];
            $i = 2;
        } elseif (false !== $i = array_search($autoload_frame, $trace, true)) {
            $caller_frame = $trace[++$i];
        } else {
            throw $e;
        }
        if (isset($caller_frame['function']) && !isset($caller_frame['class'])) {
            switch ($caller_frame['function']) {
                case 'get_class_methods':
                case 'get_class_vars':
                case 'get_parent_class':
                case 'is_a':
                case 'is_subclass_of':
                case 'class_exists':
                case 'class_implements':
                case 'class_parents':
                case 'trait_exists':
                case 'defined':
                case 'interface_exists':
                case 'method_exists':
                case 'property_exists':
                case 'is_callable':
                    return;
            }
            $props = ['file' => $caller_frame['file'] ?? null, 'line' => $caller_frame['line'] ?? null, 'trace' => \array_slice($trace, 1 + $i)];
            foreach ($props as $p => $v) {
                if (null !== $v) {
                    $r = new \ReflectionProperty(\Exception::class, $p);
                    $r->set_value($e, $v);
                }
            }
        }
        throw $e;
    }
}