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

namespace Symfony\Component\Messenger\Stamp;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final readonly class SerializerStamp implements StampInterface
{
    public function __construct(
        private array $context,
    ) {
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
