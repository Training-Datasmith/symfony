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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Http_Kernel_Extension extends Abstract_Extension
{
    public function get_functions(): array
    {
        return [new Twig_Function('render', [Http_Kernel_Runtime::class, 'renderFragment'], ['is_safe' => ['html']]), new Twig_Function('render_*', [Http_Kernel_Runtime::class, 'renderFragmentStrategy'], ['is_safe' => ['html']]), new Twig_Function('fragment_uri', [Http_Kernel_Runtime::class, 'generateFragmentUri']), new Twig_Function('controller', self::controller(...))];
    }
    public static function controller(string $controller, array $attributes = [], array $query = []): Controller_Reference
    {
        return new Controller_Reference($controller, $attributes, $query);
    }
}