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
class In_Memory_Data_Storage implements Data_Storage_Interface
{
    private array $memory = [];
    public function __construct(private readonly string $key)
    {
    }
    public function save(object|array $data): void
    {
        $this->memory[$this->key] = $data;
    }
    public function load(object|array|null $default = null): object|array|null
    {
        return $this->memory[$this->key] ?? $default;
    }
    public function clear(): void
    {
        unset($this->memory[$this->key]);
    }
}