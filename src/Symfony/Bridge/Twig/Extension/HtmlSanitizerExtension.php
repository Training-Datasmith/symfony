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

use Psr\Container\Container_Interface;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Filter;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Html_Sanitizer_Extension extends Abstract_Extension
{
    public function __construct(private readonly Container_Interface $sanitizers, private readonly string $default_sanitizer = 'default')
    {
    }
    public function get_filters(): array
    {
        return [new Twig_Filter('sanitize_html', $this->sanitize(...), ['is_safe' => ['html']])];
    }
    public function sanitize(string $html, ?string $sanitizer = null): string
    {
        return $this->sanitizers->get($sanitizer ?? $this->default_sanitizer)->sanitize($html);
    }
}