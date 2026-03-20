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
namespace Symfony\Bundle\Framework_Bundle\Data_Collector;

use Symfony\Bundle\Framework_Bundle\Controller\Redirect_Controller;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Data_Collector\Router_Data_Collector as BaseRouterDataCollector;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Router_Data_Collector extends Base_Router_Data_Collector
{
    public function guess_route(Request $request, mixed $controller): string
    {
        if (\is_array($controller)) {
            $controller = $controller[0];
        }
        if ($controller instanceof Redirect_Controller && $request->attributes->has('_route')) {
            return $request->attributes->get('_route');
        }
        return parent::guess_route($request, $controller);
    }
}