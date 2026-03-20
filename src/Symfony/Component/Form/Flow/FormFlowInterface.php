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

use Symfony\Component\Form\Clickable_Interface;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Form_Interface;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface Form_Flow_Interface extends Form_Interface
{
    /**
     * Returns the button used to submit the form.
     */
    public function get_clicked_button(): Button_Flow_Interface|Form_Interface|Clickable_Interface|null;
    /**
     * Resets the flow by clearing stored data and setting the cursor to the initial step.
     */
    public function reset(): void;
    /**
     * Moves back to a previous step in the flow.
     *
     * @param string|null $step The step to move back to, or null to move back one step
     *
     * @throws RuntimeException If the previous step cannot be determined
     */
    public function move_previous(?string $step = null): void;
    /**
     * Moves to the next step in the flow.
     *
     * @throws RuntimeException If the next step cannot be determined
     */
    public function move_next(): void;
    /**
     * Creates a new form for the current step with initial options.
     */
    public function new_step_form(): static;
    /**
     * Gets the form for the current step, handling any action if needed.
     * Returns a new step form if the current form is valid and submitted.
     */
    public function get_step_form(): static;
    /**
     * Returns the cursor that tracks the current position in the flow.
     */
    public function get_cursor(): Form_Flow_Cursor;
    /**
     * Returns the configuration for this flow.
     */
    public function get_config(): Form_Flow_Config_Interface;
    /**
     * Checks if the flow has been completed.
     */
    public function is_finished(): bool;
}