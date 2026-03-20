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

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Form_Type_Interface;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Step_Flow_Builder implements Step_Flow_Builder_Config_Interface
{
    private bool $locked = false;
    private int $priority = 0;
    private ?\Closure $skip = null;
    /**
     * @param class-string<FormTypeInterface> $type
     */
    public function __construct(private readonly string $name, private readonly string $type, private readonly array $options = [])
    {
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function get_type(): string
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }
        return $this->type;
    }
    public function get_options(): array
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }
        return $this->options;
    }
    public function get_priority(): int
    {
        return $this->priority;
    }
    public function set_priority(int $priority): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }
        $this->priority = $priority;
        return $this;
    }
    public function get_skip(): ?\Closure
    {
        return $this->skip;
    }
    public function is_skipped(mixed $data): bool
    {
        if (null === $this->skip) {
            return false;
        }
        return ($this->skip)($data);
    }
    public function set_skip(?\Closure $skip): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }
        $this->skip = $skip;
        return $this;
    }
    public function get_step_config(): Step_Flow_Config_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');
        }
        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;
        return $config;
    }
}