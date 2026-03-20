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

use Symfony\Component\Security\Http\Logout\Logout_Url_Generator;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * LogoutUrlHelper provides generator functions for the logout URL to Twig.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
final class Logout_Url_Extension extends Abstract_Extension
{
    public function __construct(private readonly Logout_Url_Generator $generator)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('logout_url', $this->get_logout_url(...)), new Twig_Function('logout_path', $this->get_logout_path(...))];
    }
    /**
     * Generates the relative logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     */
    public function get_logout_path(?string $key = null): string
    {
        return $this->generator->get_logout_path($key);
    }
    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     */
    public function get_logout_url(?string $key = null): string
    {
        return $this->generator->get_logout_url($key);
    }
}