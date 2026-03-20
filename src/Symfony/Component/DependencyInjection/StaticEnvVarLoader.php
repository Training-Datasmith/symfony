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

class Static_Env_Var_Loader implements Env_Var_Loader_Interface
{
    private array $env_vars;
    public function __construct(private readonly Env_Var_Loader_Interface $env_var_loader)
    {
    }
    public function load_env_vars(): array
    {
        return $this->env_vars ??= $this->env_var_loader->load_env_vars();
    }
}