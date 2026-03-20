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

use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Twig\Extension\Abstract_Extension;
use Twig\Node\Expression\Array_Expression;
use Twig\Node\Expression\Constant_Expression;
use Twig\Node\Node;
use Twig\Twig_Function;
/**
 * Provides integration of the Routing component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Routing_Extension extends Abstract_Extension
{
    public function __construct(private readonly Url_Generator_Interface $generator)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('url', $this->get_url(...), ['is_safe_callback' => $this->is_url_generation_safe(...)]), new Twig_Function('path', $this->get_path(...), ['is_safe_callback' => $this->is_url_generation_safe(...)])];
    }
    public function get_path(string $name, array $parameters = [], bool $relative = false): string
    {
        return $this->generator->generate($name, $parameters, $relative ? Url_Generator_Interface::RELATIVE_PATH : Url_Generator_Interface::ABSOLUTE_PATH);
    }
    public function get_url(string $name, array $parameters = [], bool $scheme_relative = false): string
    {
        return $this->generator->generate($name, $parameters, $scheme_relative ? Url_Generator_Interface::NETWORK_PATH : Url_Generator_Interface::ABSOLUTE_URL);
    }
    /**
     * Determines at compile time whether the generated URL will be safe and thus
     * saving the unneeded automatic escaping for performance reasons.
     *
     * The URL generation process percent encodes non-alphanumeric characters. So there is no risk
     * that malicious/invalid characters are part of the URL. The only character within a URL that
     * must be escaped in html is the ampersand ("&") which separates query params. So we cannot mark
     * the URL generation as always safe, but only when we are sure there won't be multiple query
     * params. This is the case when there are none or only one constant parameter given.
     * E.g. we know beforehand this will be safe:
     * - path('route')
     * - path('route', {'param': 'value'})
     * But the following may not:
     * - path('route', var)
     * - path('route', {'param': ['val1', 'val2'] }) // a sub-array
     * - path('route', {'param1': 'value1', 'param2': 'value2'})
     * If param1 and param2 reference placeholder in the route, it would still be safe. But we don't know.
     *
     * @param Node $argsNode The arguments of the path/url function
     *
     * @return array An array with the contexts the URL is safe
     */
    public function is_url_generation_safe(Node $args_node): array
    {
        // support named arguments
        $params_node = $args_node->has_node('parameters') ? $args_node->get_node('parameters') : ($args_node->has_node(1) ? $args_node->get_node(1) : null);
        if (null === $params_node || $params_node instanceof Array_Expression && \count($params_node) <= 2 && (!$params_node->has_node(1) || $params_node->get_node(1) instanceof Constant_Expression)) {
            return ['html'];
        }
        return [];
    }
}