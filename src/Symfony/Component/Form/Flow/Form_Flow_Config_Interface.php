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

use Symfony\Component\Form\Flow\Data_Storage\Data_Storage_Interface;
use Symfony\Component\Form\Flow\Step_Accessor\Step_Accessor_Interface;
use Symfony\Component\Form\Form_Config_Interface;
/**
 * The configuration of a {@link FormFlow} object.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface Form_Flow_Config_Interface extends Form_Config_Interface
{
    /**
     * Checks if a step with the given name exists.
     */
    public function has_step(string $name): bool;
    /**
     * Returns the step with the given name.
     */
    public function get_step(string $name): Step_Flow_Config_Interface;
    /**
     * Returns all steps.
     *
     * @return array<string, StepFlowConfigInterface>
     */
    public function get_steps(): array;
    /**
     * Returns the name of the initial step.
     */
    public function get_initial_step(): string;
    /**
     * Returns the initial options for the form flow.
     *
     * @return array<string, mixed>
     */
    public function get_initial_options(): array;
    /**
     * Returns the data storage for the form flow.
     */
    public function get_data_storage(): Data_Storage_Interface;
    /**
     * Returns the step accessor for the form flow.
     */
    public function get_step_accessor(): Step_Accessor_Interface;
    /**
     * Checks if the form flow is configured to auto reset once it's finished.
     */
    public function is_auto_reset(): bool;
}