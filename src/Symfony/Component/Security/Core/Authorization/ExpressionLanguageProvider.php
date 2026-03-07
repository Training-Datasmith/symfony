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

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_authenticated', static fn (): string => '$auth_checker->isGranted("IS_AUTHENTICATED")', static fn (array $variables) => $variables['auth_checker']->isGranted('IS_AUTHENTICATED')),

            new ExpressionFunction('is_fully_authenticated', static fn (): string => '$token && $auth_checker->isGranted("IS_AUTHENTICATED_FULLY")', static fn (array $variables): bool => $variables['token'] && $variables['auth_checker']->isGranted('IS_AUTHENTICATED_FULLY')),

            new ExpressionFunction('is_granted', static fn (string $attributes, $object = 'null'): string => \sprintf('$auth_checker->isGranted(%s, %s)', $attributes, $object), static fn (array $variables, $attributes, $object = null) => $variables['auth_checker']->isGranted($attributes, $object)),

            new ExpressionFunction('is_remember_me', static fn (): string => '$token && $auth_checker->isGranted("IS_REMEMBERED")', static fn (array $variables): bool => $variables['token'] && $variables['auth_checker']->isGranted('IS_REMEMBERED')),
        ];
    }
}
