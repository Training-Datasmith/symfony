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
use Symfony\Component\Form\Form_Interface;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface Button_Flow_Interface extends Form_Interface, Clickable_Interface
{
    /**
     * Executes the callable handler.
     */
    public function handle(): void;
    /**
     * Checks if the callable handler was already called.
     */
    public function is_handled(): bool;
    /**
     * Checks if the button's action is 'reset'.
     */
    public function is_reset_action(): bool;
    /**
     * Checks if the button's action is 'previous'.
     */
    public function is_previous_action(): bool;
    /**
     * Checks if the button's action is 'next'.
     */
    public function is_next_action(): bool;
    /**
     * Checks if the button's action is 'finish'.
     */
    public function is_finish_action(): bool;
    /**
     * Checks if the button is configured to clear submission data.
     */
    public function is_clear_submission(): bool;
}