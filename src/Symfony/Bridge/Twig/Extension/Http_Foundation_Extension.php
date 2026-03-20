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

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Url_Helper;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * Twig extension for the Symfony HttpFoundation component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Http_Foundation_Extension extends Abstract_Extension
{
    public function __construct(private readonly Url_Helper $url_helper)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('absolute_url', $this->generate_absolute_url(...)), new Twig_Function('relative_path', $this->generate_relative_path(...))];
    }
    /**
     * Returns the absolute URL for the given absolute or relative path.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @see Request::getUriForPath()
     */
    public function generate_absolute_url(string $path): string
    {
        return $this->url_helper->get_absolute_url($path);
    }
    /**
     * Returns a relative path based on the current Request.
     *
     * This method returns the path unchanged if no request is available.
     *
     * @see Request::getRelativeUriForPath()
     */
    public function generate_relative_path(string $path): string
    {
        return $this->url_helper->get_relative_path($path);
    }
}