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

use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Flow\Data_Storage\Data_Storage_Interface;
use Symfony\Component\Form\Flow\Step_Accessor\Step_Accessor_Interface;
use Symfony\Component\Form\Form_Builder_Interface;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @extends \Traversable<string, FormBuilderInterface>
 */
interface Form_Flow_Builder_Interface extends Form_Builder_Interface, Form_Flow_Config_Interface
{
    /**
     * Creates a new step builder.
     */
    public function create_step(string $name, string $type = Form_Type::class, array $options = []): Step_Flow_Builder_Config_Interface;
    /**
     * Adds a step to the form flow.
     */
    public function add_step(Step_Flow_Builder_Config_Interface|string $name, string $type = Form_Type::class, array $options = [], ?callable $skip = null, int $priority = 0): static;
    /**
     * Removes a step from the form flow.
     */
    public function remove_step(string $name): static;
    /**
     * Returns a step builder by name.
     */
    public function get_step(string $name): Step_Flow_Builder_Config_Interface;
    /**
     * Returns all step builders.
     *
     * @return array<string, StepFlowBuilderConfigInterface>
     */
    public function get_steps(): array;
    /**
     * Sets the initial options for the form flow.
     *
     * @param array<string, mixed> $options
     */
    public function set_initial_options(array $options): static;
    /**
     * Sets the data storage for the form flow.
     */
    public function set_data_storage(Data_Storage_Interface $data_storage): static;
    /**
     * Sets the step accessor for the form flow.
     */
    public function set_step_accessor(Step_Accessor_Interface $step_accessor): static;
    /**
     * Creates and returns the form flow instance.
     */
    public function get_form(): Form_Flow_Interface;
}