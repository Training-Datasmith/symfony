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

use Symfony\Component\Routing\Matcher\Compiled_Url_Matcher;
use Symfony\Component\Routing\Matcher\Redirectable_Url_Matcher_Interface;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Redirectable_Compiled_Url_Matcher extends Compiled_Url_Matcher implements Redirectable_Url_Matcher_Interface
{
    public function redirect(string $path, string $route, ?string $scheme = null): array
    {
        return ['_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction', 'path' => $path, 'permanent' => true, 'scheme' => $scheme, 'httpPort' => $this->context->get_http_port(), 'httpsPort' => $this->context->get_https_port(), '_route' => $route, '_route_mapping' => []];
    }
}