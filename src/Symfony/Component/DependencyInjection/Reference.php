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
namespace Symfony\Component\Dependency_Injection;

/**
 * Reference represents a service reference.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Reference implements \Stringable
{
    public function __construct(private readonly string $id, private int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE)
    {
    }
    public function __toString(): string
    {
        return $this->id;
    }
    /**
     * Returns the behavior to be used when the service does not exist.
     */
    public function get_invalid_behavior(): int
    {
        return $this->invalid_behavior ??= Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
    }
    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $k => $v) {
            if (false !== $i = strrpos((string) $k, "\x00")) {
                $k = substr((string) $k, 1 + $i);
            }
            if ('invalidBehavior' === $k && Container_Interface::EXCEPTION_ON_INVALID_REFERENCE === $v) {
                continue;
            }
            $data[$k] = $v;
        }
        return $data;
    }
}