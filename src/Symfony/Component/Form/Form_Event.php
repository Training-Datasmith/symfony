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
namespace Symfony\Component\Form;

use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Event extends Event
{
    public function __construct(private readonly Form_Interface $form, protected mixed $data)
    {
    }
    /**
     * Returns the form at the source of the event.
     */
    public function get_form(): Form_Interface
    {
        return $this->form;
    }
    /**
     * Returns the data associated with this event.
     */
    public function get_data(): mixed
    {
        return $this->data;
    }
    /**
     * Allows updating with some filtered data.
     */
    public function set_data(mixed $data): void
    {
        $this->data = $data;
    }
}