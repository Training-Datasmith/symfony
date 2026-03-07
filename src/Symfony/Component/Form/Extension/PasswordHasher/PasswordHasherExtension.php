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

namespace Symfony\Component\Form\Extension\PasswordHasher;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener;

/**
 * Integrates the PasswordHasher component with the Form library.
 *
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class PasswordHasherExtension extends AbstractExtension
{
    public function __construct(
        private readonly PasswordHasherListener $passwordHasherListener,
    ) {
    }

    protected function loadTypeExtensions(): array
    {
        return [
            new Type\FormTypePasswordHasherExtension($this->passwordHasherListener),
            new Type\PasswordTypePasswordHasherExtension($this->passwordHasherListener),
        ];
    }
}
