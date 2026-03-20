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

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface Step_Flow_Builder_Config_Interface extends Step_Flow_Config_Interface
{
    /**
     * Returns the form type class name for the step.
     */
    public function get_type(): string;
    /**
     * Returns the form options for the step.
     */
    public function get_options(): array;
    /**
     * Returns the priority of the step.
     */
    public function get_priority(): int;
    /**
     * Sets the priority of the step.
     */
    public function set_priority(int $priority): static;
    /**
     * Sets the closure that determines if the step should be skipped.
     */
    public function set_skip(?\Closure $skip): static;
    /**
     * Returns a StepFlowConfigInterface instance for the step.
     */
    public function get_step_config(): Step_Flow_Config_Interface;
}