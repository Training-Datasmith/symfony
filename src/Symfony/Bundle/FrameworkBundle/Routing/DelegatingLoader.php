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
namespace Symfony\Bundle\Framework_Bundle\Routing;

use Symfony\Component\Config\Exception\Loader_Load_Exception;
use Symfony\Component\Config\Loader\Delegating_Loader as BaseDelegatingLoader;
use Symfony\Component\Config\Loader\Loader_Resolver_Interface;
use Symfony\Component\Routing\Route_Collection;
/**
 * DelegatingLoader delegates route loading to other loaders using a loader resolver.
 *
 * This implementation resolves the _controller attribute from the short notation
 * to the fully-qualified form (from a:b:c to class::method).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Delegating_Loader extends Base_Delegating_Loader
{
    private bool $loading = false;
    public function __construct(Loader_Resolver_Interface $resolver, private readonly array $default_options = [], private readonly array $default_requirements = [])
    {
        parent::__construct($resolver);
    }
    public function load(mixed $resource, ?string $type = null): Route_Collection
    {
        if ($this->loading) {
            // This can happen if a fatal error occurs in parent::load().
            // Here is the scenario:
            // - while routes are being loaded by parent::load() below, a fatal error
            //   occurs (e.g. parse error in a controller while loading annotations);
            // - PHP abruptly empties the stack trace, bypassing all catch/finally blocks;
            //   it then calls the registered shutdown functions;
            // - the ErrorHandler catches the fatal error and re-injects it for rendering
            //   thanks to HttpKernel->terminateWithException() (that calls handleException());
            // - at this stage, if we try to load the routes again, we must prevent
            //   the fatal error from occurring a second time,
            //   otherwise the PHP process would be killed immediately;
            // - while rendering the exception page, the router can be required
            //   (by e.g. the web profiler that needs to generate a URL);
            // - this handles the case and prevents the second fatal error
            //   by triggering an exception beforehand.
            throw new Loader_Load_Exception($resource, null, 0, null, $type);
        }
        $this->loading = true;
        try {
            $collection = parent::load($resource, $type);
        } finally {
            $this->loading = false;
        }
        foreach ($collection->all() as $route) {
            if ($this->default_options) {
                $route->set_options($route->get_options() + $this->default_options);
            }
            if ($this->default_requirements) {
                $route->set_requirements($route->get_requirements() + $this->default_requirements);
            }
            if (!\is_string($controller = $route->get_default('_controller'))) {
                continue;
            }
            if (str_contains($controller, '::')) {
                continue;
            }
            $route->set_default('_controller', $controller);
        }
        return $collection;
    }
}