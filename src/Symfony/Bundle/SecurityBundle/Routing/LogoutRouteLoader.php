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
namespace Symfony\Bundle\Security_Bundle\Routing;

use Symfony\Component\Dependency_Injection\Config\Container_Parameters_Resource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Route_Collection;
final readonly class Logout_Route_Loader
{
    /**
     * @param array<string, string> $logoutUris    Logout URIs indexed by the corresponding firewall name
     * @param string                $parameterName Name of the container parameter containing {@see $logoutUris} value
     */
    public function __construct(private array $logout_uris, private string $parameter_name)
    {
    }
    public function __invoke(): Route_Collection
    {
        $collection = new Route_Collection();
        $collection->add_resource(new Container_Parameters_Resource([$this->parameter_name => $this->logout_uris]));
        $route_names = [];
        foreach ($this->logout_uris as $firewall_name => $logout_path) {
            $route_name = '_logout_' . $firewall_name;
            if (isset($route_names[$logout_path])) {
                $collection->add_alias($route_name, $route_names[$logout_path]);
            } else {
                $route_names[$logout_path] = $route_name;
                $collection->add($route_name, new Route($logout_path));
            }
        }
        return $collection;
    }
}