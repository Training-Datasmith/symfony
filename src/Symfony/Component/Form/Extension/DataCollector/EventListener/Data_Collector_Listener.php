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
namespace Symfony\Component\Form\Extension\Data_Collector\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Extension\Data_Collector\Form_Data_Collector_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
/**
 * Listener that invokes a data collector for the {@link FormEvents::POST_SET_DATA}
 * and {@link FormEvents::POST_SUBMIT} events.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Data_Collector_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly Form_Data_Collector_Interface $data_collector)
    {
    }
    public static function get_subscribed_events(): array
    {
        return [
            // Low priority in order to be called as late as possible
            Form_Events::POST_SET_DATA => ['postSetData', -255],
            // Low priority in order to be called as late as possible
            Form_Events::POST_SUBMIT => ['postSubmit', -255],
        ];
    }
    /**
     * Listener for the {@link FormEvents::POST_SET_DATA} event.
     */
    public function post_set_data(Form_Event $event): void
    {
        if ($event->get_form()->is_root()) {
            // Collect basic information about each form
            $this->data_collector->collect_configuration($event->get_form());
            // Collect the default data
            $this->data_collector->collect_default_data($event->get_form());
        }
    }
    /**
     * Listener for the {@link FormEvents::POST_SUBMIT} event.
     */
    public function post_submit(Form_Event $event): void
    {
        if ($event->get_form()->is_root()) {
            // Collect the submitted data of each form
            $this->data_collector->collect_submitted_data($event->get_form());
            // Assemble a form tree
            // This is done again after the view is built, but we need it here as the view is not always created.
            $this->data_collector->build_preliminary_form_tree($event->get_form());
        }
    }
}