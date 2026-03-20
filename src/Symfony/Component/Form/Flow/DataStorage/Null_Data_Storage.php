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
namespace Symfony\Component\Form\Flow\Data_Storage;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
final class Null_Data_Storage implements Data_Storage_Interface
{
    public function save(object|array $data): void
    {
        // no-op
    }
    public function load(object|array|null $default = null): object|array|null
    {
        return $default;
    }
    public function clear(): void
    {
        // no-op
    }
}