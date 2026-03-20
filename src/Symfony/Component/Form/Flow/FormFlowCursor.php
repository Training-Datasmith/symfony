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
namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Exception\InvalidArgumentException;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Form_Flow_Cursor
{
    /**
     * @param array<string> $steps
     */
    public function __construct(private readonly array $steps, private readonly string $current_step)
    {
        if (!\in_array($current_step, $steps, true)) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist. Available steps are: "%s".', $current_step, implode('", "', $steps)));
        }
    }
    public function get_steps(): array
    {
        return $this->steps;
    }
    public function get_total_steps(): int
    {
        return \count($this->steps);
    }
    public function get_step_index(): int
    {
        return (int) array_search($this->current_step, $this->steps, true);
    }
    public function get_first_step(): string
    {
        return $this->steps[0];
    }
    public function get_previous_step(): ?string
    {
        $current_pos = array_search($this->current_step, $this->steps, true);
        return $this->steps[$current_pos - 1] ?? null;
    }
    public function get_current_step(): string
    {
        return $this->current_step;
    }
    public function with_current_step(string $step): self
    {
        return new self($this->steps, $step);
    }
    public function get_next_step(): ?string
    {
        $current_pos = array_search($this->current_step, $this->steps, true);
        return $this->steps[$current_pos + 1] ?? null;
    }
    public function get_last_step(): string
    {
        return $this->steps[\count($this->steps) - 1];
    }
    public function is_first_step(): bool
    {
        return 0 === array_search($this->current_step, $this->steps, true);
    }
    public function is_last_step(): bool
    {
        $current_pos = array_search($this->current_step, $this->steps, true);
        return \count($this->steps) === $current_pos + 1;
    }
    public function can_move_back(): bool
    {
        return null !== $this->get_previous_step();
    }
    public function can_move_next(): bool
    {
        return null !== $this->get_next_step();
    }
}