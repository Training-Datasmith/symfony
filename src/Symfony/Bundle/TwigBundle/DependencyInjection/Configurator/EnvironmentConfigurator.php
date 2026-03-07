<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Configurator;

use Symfony\Bridge\Twig\UndefinedCallableHandler;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Twig environment configurator.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class EnvironmentConfigurator
{
    public function __construct(
        private readonly string $dateFormat,
        private readonly string $intervalFormat,
        private readonly ?string $timezone,
        private readonly int $decimals,
        private readonly string $decimalPoint,
        private readonly string $thousandsSeparator,
    ) {
    }

    public function configure(Environment $environment): void
    {
        $environment->getExtension(CoreExtension::class)->setDateFormat($this->dateFormat, $this->intervalFormat);

        if (null !== $this->timezone) {
            $environment->getExtension(CoreExtension::class)->setTimezone($this->timezone);
        }

        $environment->getExtension(CoreExtension::class)->setNumberFormat($this->decimals, $this->decimalPoint, $this->thousandsSeparator);

        // wrap UndefinedCallableHandler in closures for lazy-autoloading
        $environment->registerUndefinedFilterCallback(static fn (string $name): \Twig\TwigFilter|false => UndefinedCallableHandler::onUndefinedFilter($name));
        $environment->registerUndefinedFunctionCallback(static fn (string $name): \Twig\TwigFunction|false => UndefinedCallableHandler::onUndefinedFunction($name));
    }
}
