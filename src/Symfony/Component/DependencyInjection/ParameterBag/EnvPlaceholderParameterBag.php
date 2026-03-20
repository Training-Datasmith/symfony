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
namespace Symfony\Component\Dependency_Injection\Parameter_Bag;

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Env_Placeholder_Parameter_Bag extends Parameter_Bag
{
    private string $env_placeholder_unique_prefix;
    private array $env_placeholders = [];
    private array $unused_env_placeholders = [];
    private array $provided_types = [];
    private static int $counter = 0;
    public function get(string $name): array|bool|string|int|float|\Unit_Enum|null
    {
        if (str_starts_with($name, 'env(') && str_ends_with($name, ')') && 'env()' !== $name) {
            $env = substr($name, 4, -1);
            if (isset($this->env_placeholders[$env])) {
                foreach ($this->env_placeholders[$env] as $placeholder) {
                    return $placeholder;
                    // return first result
                }
            }
            if (isset($this->unused_env_placeholders[$env])) {
                foreach ($this->unused_env_placeholders[$env] as $placeholder) {
                    return $placeholder;
                    // return first result
                }
            }
            if (!preg_match('/^(?:[-.\w\\\\]*+:)*+[\w.]*+$/', $env)) {
                throw new InvalidArgumentException(\sprintf('The given env var name "%s" contains invalid characters (allowed characters: letters, digits, hyphens, backslashes, dots and colons).', $name));
            }
            if ($this->has($name) && null !== ($default_value = parent::get($name)) && !\is_string($default_value)) {
                throw new RuntimeException(\sprintf('The default value of an env() parameter must be a string or null, but "%s" given to "%s".', get_debug_type($default_value), $name));
            }
            $unique_name = hash('xxh128', $name . '_' . self::$counter++);
            $placeholder = \sprintf('%s_%s_%s', $this->get_env_placeholder_unique_prefix(), strtr($env, ':-.\\', '____'), $unique_name);
            $this->env_placeholders[$env][$placeholder] = $placeholder;
            return $placeholder;
        }
        return parent::get($name);
    }
    /**
     * Gets the common env placeholder prefix for env vars created by this bag.
     */
    public function get_env_placeholder_unique_prefix(): string
    {
        if (!isset($this->env_placeholder_unique_prefix)) {
            $reproducible_entropy = unserialize(serialize($this->parameters));
            array_walk_recursive($reproducible_entropy, static function (&$v): void {
                $v = null;
            });
            $this->env_placeholder_unique_prefix = 'env_' . substr(hash('xxh128', serialize($reproducible_entropy)), -16);
        }
        return $this->env_placeholder_unique_prefix;
    }
    /**
     * Returns the map of env vars used in the resolved parameter values to their placeholders.
     *
     * @return string[][] A map of env var names to their placeholders
     */
    public function get_env_placeholders(): array
    {
        return $this->env_placeholders;
    }
    public function get_unused_env_placeholders(): array
    {
        return $this->unused_env_placeholders;
    }
    public function clear_unused_env_placeholders(): void
    {
        $this->unused_env_placeholders = [];
    }
    /**
     * Merges the env placeholders of another EnvPlaceholderParameterBag.
     */
    public function merge_env_placeholders(self $bag): void
    {
        if ($new_placeholders = $bag->get_env_placeholders()) {
            $this->env_placeholders += $new_placeholders;
            foreach ($new_placeholders as $env => $placeholders) {
                $this->env_placeholders[$env] += $placeholders;
            }
        }
        if ($new_unused_placeholders = $bag->get_unused_env_placeholders()) {
            $this->unused_env_placeholders += $new_unused_placeholders;
            foreach ($new_unused_placeholders as $env => $placeholders) {
                $this->unused_env_placeholders[$env] += $placeholders;
            }
        }
    }
    /**
     * Maps env prefixes to their corresponding PHP types.
     */
    public function set_provided_types(array $provided_types): void
    {
        $this->provided_types = $provided_types;
    }
    /**
     * Gets the PHP types corresponding to env() parameter prefixes.
     *
     * @return string[][]
     */
    public function get_provided_types(): array
    {
        return $this->provided_types;
    }
    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }
        parent::resolve();
        foreach ($this->env_placeholders as $env => $placeholders) {
            if ($this->has($name = "env({$env})") && null !== ($default = $this->parameters[$name]) && !\is_string($default)) {
                throw new RuntimeException(\sprintf('The default value of env parameter "%s" must be a string or null, "%s" given.', $env, get_debug_type($default)));
            }
        }
    }
}