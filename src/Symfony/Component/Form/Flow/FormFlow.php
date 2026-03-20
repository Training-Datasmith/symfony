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
use Symfony\Component\Form\Exception\Already_Submitted_Exception;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Form_Interface;
/**
 * FormFlow represents a multistep form.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @implements \IteratorAggregate<string, FormInterface>
 */
class Form_Flow extends Form implements Form_Flow_Interface
{
    private ?Button_Flow_Interface $clicked_flow_button = null;
    private bool $finished = false;
    public function __construct(private readonly Form_Flow_Config_Interface $config, private Form_Flow_Cursor $cursor)
    {
        parent::__construct($config);
    }
    public function submit(mixed $submitted_data, bool $clear_missing = true): static
    {
        if ($this->is_submitted()) {
            throw new Already_Submitted_Exception('A form can only be submitted once.');
        }
        if (!\is_array($submitted_data)) {
            throw new Transformation_Failed_Exception('The submitted data must be an array.');
        }
        if (!$this->is_current_step_submitted($submitted_data)) {
            // the submitted data doesn't match the current step,
            // it's probably a reload of a POST visit from a different step
            return $this;
        }
        $this->set_clicked_flow_button($submitted_data, $this);
        parent::submit($submitted_data, $clear_missing);
        if (!$this->clicked_flow_button || !$this->is_submitted() || !$this->is_valid()) {
            return $this;
        }
        $this->finished = $this->clicked_flow_button->is_finish_action();
        if ($this->finished && $this->config->is_auto_reset()) {
            $this->reset();
        }
        return $this;
    }
    public function reset(): void
    {
        $this->config->get_data_storage()->clear();
        $this->cursor = $this->cursor->with_current_step($this->config->get_initial_step());
    }
    public function move_previous(?string $step = null): void
    {
        if ($step) {
            $this->move_back_to($step);
            return;
        }
        if (!$this->move(static fn(Form_Flow_Cursor $cursor): ?string => $cursor->get_previous_step())) {
            throw new RuntimeException('Cannot determine previous step.');
        }
    }
    public function move_next(): void
    {
        if (!$this->move(static fn(Form_Flow_Cursor $cursor): ?string => $cursor->get_next_step())) {
            throw new RuntimeException('Cannot determine next step.');
        }
    }
    public function new_step_form(): static
    {
        return $this->config->get_form_factory()->create_named($this->config->get_name(), $this->config->get_type()->get_inner_type()::class, $this->get_data(), $this->config->get_initial_options());
    }
    public function get_step_form(): static
    {
        if (!$this->is_submitted() || !$this->is_valid()) {
            return $this;
        }
        if ($this->clicked_flow_button && !$this->clicked_flow_button->is_handled()) {
            $this->clicked_flow_button->handle();
        }
        if (!$this->is_valid()) {
            return $this;
        }
        return $this->new_step_form();
    }
    public function get_cursor(): Form_Flow_Cursor
    {
        return $this->cursor;
    }
    public function get_config(): Form_Flow_Config_Interface
    {
        return $this->config;
    }
    public function is_finished(): bool
    {
        return $this->finished;
    }
    public function get_clicked_button(): Button_Flow_Interface|Form_Interface|Clickable_Interface|null
    {
        return parent::get_clicked_button() ?? $this->clicked_flow_button;
    }
    private function set_clicked_flow_button(mixed $submitted_data, Form_Interface $form): void
    {
        if (!\is_array($submitted_data)) {
            return;
        }
        foreach ($form as $name => $child) {
            if (!\array_key_exists($name, $submitted_data)) {
                continue;
            }
            if ($child->count() > 0) {
                $this->set_clicked_flow_button($submitted_data[$name], $child);
                if ($this->clicked_flow_button) {
                    return;
                }
                continue;
            }
            if (!$child instanceof Button_Flow_Interface) {
                continue;
            }
            $child->submit($submitted_data[$name]);
            if ($child->is_clicked()) {
                $this->clicked_flow_button = $child;
                break;
            }
        }
    }
    private function move_back_to(string $step): void
    {
        $steps = $this->cursor->get_steps();
        if (false === $target_index = array_search($step, $steps)) {
            throw new InvalidArgumentException(\sprintf('Step "%s" does not exist.', $step));
        }
        $current_step = $this->cursor->get_current_step();
        $current_index = $this->cursor->get_step_index();
        if ($target_index === $current_index) {
            return;
        }
        if ($target_index > $current_index) {
            throw new RuntimeException(\sprintf('Cannot move back to step "%s" because it is ahead of the current step "%s".', $step, $current_step));
        }
        while ($target_index < $current_index) {
            $this->move_previous();
            $current_index = $this->cursor->get_step_index();
        }
        if ($target_index > $current_index) {
            throw new RuntimeException(\sprintf('Cannot move back to step "%s" because it is a skipped step.', $step));
        }
    }
    private function move(\Closure $direction): bool
    {
        $data = $this->get_data();
        $cursor = $this->cursor;
        while (true) {
            if (null === $new_step = $direction($cursor)) {
                return false;
            }
            if ($cursor->get_current_step() === $new_step) {
                return true;
            }
            $cursor = $cursor->with_current_step($new_step);
            if (!$this->config->get_step($new_step)->is_skipped($data)) {
                break;
            }
            if ($cursor->is_last_step()) {
                $this->finished = true;
                if ($this->config->is_auto_reset()) {
                    $this->reset();
                    return true;
                }
                break;
            }
        }
        $this->cursor = $cursor;
        $this->config->get_step_accessor()->set_step($data, $new_step);
        $this->config->get_data_storage()->save($data);
        return true;
    }
    private function is_current_step_submitted(array $submitted_data): bool
    {
        foreach ($this->cursor->get_steps() as $step) {
            if (\array_key_exists($step, $submitted_data)) {
                return $step === $this->cursor->get_current_step();
            }
        }
        return true;
    }
}