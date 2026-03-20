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
namespace Symfony\Component\Dependency_Injection\Extension;

use Symfony\Component\Dependency_Injection\Container_Builder;
interface Prepend_Extension_Interface
{
    /**
     * Allow an extension to prepend the extension configurations.
     */
    public function prepend(Container_Builder $container): void;
}