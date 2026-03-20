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
namespace Symfony\Bridge\Php_Unit\Deprecation_Error_Handler;

/**
 * @internal
 */
final class Deprecation_Notice
{
    private int $count = 0;
    /**
     * @var int[]
     */
    private array $counts_by_caller = [];
    public function add_object_occurrence($class, $method): void
    {
        if (!isset($this->counts_by_caller["{$class}::{$method}"])) {
            $this->counts_by_caller["{$class}::{$method}"] = 0;
        }
        ++$this->counts_by_caller["{$class}::{$method}"];
        ++$this->count;
    }
    public function add_procedural_occurrence(): void
    {
        ++$this->count;
    }
    public function get_counts_by_caller(): array
    {
        return $this->counts_by_caller;
    }
    public function count(): int
    {
        return $this->count;
    }
}