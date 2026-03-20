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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Interface that must be implemented by compilation passes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface Compiler_Pass_Interface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(Container_Builder $container): void;
}