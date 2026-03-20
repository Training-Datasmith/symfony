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
namespace Symfony\Bundle\Twig_Bundle\Dependency_Injection\Configurator;

use Symfony\Bridge\Twig\Undefined_Callable_Handler;
use Twig\Environment;
use Twig\Extension\Core_Extension;
/**
 * Twig environment configurator.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class Environment_Configurator
{
    public function __construct(private readonly string $date_format, private readonly string $interval_format, private readonly ?string $timezone, private readonly int $decimals, private readonly string $decimal_point, private readonly string $thousands_separator)
    {
    }
    public function configure(Environment $environment): void
    {
        $environment->get_extension(Core_Extension::class)->set_date_format($this->date_format, $this->interval_format);
        if (null !== $this->timezone) {
            $environment->get_extension(Core_Extension::class)->set_timezone($this->timezone);
        }
        $environment->get_extension(Core_Extension::class)->set_number_format($this->decimals, $this->decimal_point, $this->thousands_separator);
        // wrap UndefinedCallableHandler in closures for lazy-autoloading
        $environment->register_undefined_filter_callback(static fn(string $name): \Twig\Twig_Filter|false => Undefined_Callable_Handler::on_undefined_filter($name));
        $environment->register_undefined_function_callback(static fn(string $name): \Twig\Twig_Function|false => Undefined_Callable_Handler::on_undefined_function($name));
    }
}