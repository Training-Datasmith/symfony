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

use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Submit_Button;
/**
 * A button that submits the form and handles an action.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Button_Flow extends Submit_Button implements Button_Flow_Interface
{
    private mixed $data = null;
    private bool $handled = false;
    public function submit(array|string|null $submitted_data, bool $clear_missing = true): static
    {
        if ($this->is_submitted()) {
            return $this;
            // ignore double submit
        }
        parent::submit($submitted_data, $clear_missing);
        if ($this->is_submitted()) {
            $this->data = $submitted_data;
        }
        return $this;
    }
    public function get_view_data(): mixed
    {
        return $this->data;
    }
    public function handle(): void
    {
        /** @var FormInterface $form */
        $form = $this->get_parent();
        $data = $form->get_data();
        while ($form && !$form instanceof Form_Flow_Interface) {
            $form = $form->get_parent();
        }
        $handler = $this->get_config()->get_option('handler');
        $handler($data, $this, $form);
        $this->handled = true;
    }
    public function is_handled(): bool
    {
        return $this->handled;
    }
    public function is_reset_action(): bool
    {
        return 'reset' === $this->get_config()->get_attribute('action');
    }
    public function is_previous_action(): bool
    {
        return 'previous' === $this->get_config()->get_attribute('action');
    }
    public function is_next_action(): bool
    {
        return 'next' === $this->get_config()->get_attribute('action');
    }
    public function is_finish_action(): bool
    {
        return 'finish' === $this->get_config()->get_attribute('action');
    }
    public function is_clear_submission(): bool
    {
        return $this->get_config()->get_option('clear_submission');
    }
}