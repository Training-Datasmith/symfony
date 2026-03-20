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
namespace Symfony\Bridge\Doctrine\Data_Collector;

final readonly class Object_Parameter
{
    private bool $stringable;
    private string $class;
    public function __construct(private object $object, private ?\Throwable $error)
    {
        $this->stringable = $this->object instanceof \Stringable;
        $this->class = $object::class;
    }
    public function get_object(): object
    {
        return $this->object;
    }
    public function get_error(): ?\Throwable
    {
        return $this->error;
    }
    public function is_stringable(): bool
    {
        return $this->stringable;
    }
    public function get_class(): string
    {
        return $this->class;
    }
}