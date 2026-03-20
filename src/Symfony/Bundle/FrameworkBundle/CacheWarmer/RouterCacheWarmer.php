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
namespace Symfony\Bundle\Framework_Bundle\Cache_Warmer;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
use Symfony\Component\Http_Kernel\Cache_Warmer\Warmable_Interface;
use Symfony\Component\Routing\Router_Interface;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
/**
 * Generates the router matcher and generator classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Router_Cache_Warmer implements Cache_Warmer_Interface, Service_Subscriber_Interface
{
    /**
     * As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
     */
    public function __construct(private readonly Container_Interface $container)
    {
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        if (!$build_dir) {
            return [];
        }
        $router = $this->container->get('router');
        if ($router instanceof Warmable_Interface) {
            return $router->warm_up($cache_dir, $build_dir);
        }
        throw new \LogicException(\sprintf('The router "%s" cannot be warmed up because it does not implement "%s".', get_debug_type($router), Warmable_Interface::class));
    }
    public function is_optional(): bool
    {
        return true;
    }
    public static function get_subscribed_services(): array
    {
        return ['router' => Router_Interface::class];
    }
}