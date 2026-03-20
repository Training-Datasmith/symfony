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
namespace Symfony\Component\Dependency_Injection\Attribute;

use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Attribute to wrap a service in a closure that returns it.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire_Service_Closure extends Autowire
{
    /**
     * @param string $service The service id to wrap in the closure
     */
    public function __construct(string $service)
    {
        parent::__construct(new Service_Closure_Argument(new Reference($service)));
    }
}