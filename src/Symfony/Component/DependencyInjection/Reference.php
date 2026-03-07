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

namespace Symfony\Component\DependencyInjection;

/**
 * Reference represents a service reference.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Reference implements \Stringable
{
    public function __construct(
        private readonly string $id,
        private int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * Returns the behavior to be used when the service does not exist.
     */
    public function getInvalidBehavior(): int
    {
        return $this->invalidBehavior ??= ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
    }

    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $k => $v) {
            if (false !== $i = strrpos((string) $k, "\0")) {
                $k = substr((string) $k, 1 + $i);
            }
            if ('invalidBehavior' === $k && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $v) {
                continue;
            }
            $data[$k] = $v;
        }

        return $data;
    }
}
