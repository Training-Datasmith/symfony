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
namespace Symfony\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * The EnvVarProcessorInterface is implemented by objects that manage environment-like variables.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Env_Var_Processor_Interface
{
    /**
     * Returns the value of the given variable as managed by the current instance.
     *
     * @param string                  $prefix The namespace of the variable; when the empty string is passed, null values should be kept as is
     * @param string                  $name   The name of the variable within the namespace
     * @param \Closure(string): mixed $getEnv A closure that allows fetching more env vars
     *
     * @throws RuntimeException on error
     */
    public function get_env(string $prefix, string $name, \Closure $get_env): mixed;
    /**
     * @return array<string, string> The PHP-types managed by getEnv(), keyed by prefixes
     */
    public static function get_provided_types(): array;
}