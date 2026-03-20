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
namespace Symfony\Component\Form\Extension\Core\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Merge_Collection_Listener implements Event_Subscriber_Interface
{
    /**
     * @param bool $allowAdd    Whether values might be added to the collection
     * @param bool $allowDelete Whether values might be removed from the collection
     */
    public function __construct(private readonly bool $allow_add = false, private readonly bool $allow_delete = false)
    {
    }
    public static function get_subscribed_events(): array
    {
        return [Form_Events::SUBMIT => 'onSubmit'];
    }
    public function on_submit(Form_Event $event): void
    {
        $data_to_merge_into = $event->get_form()->get_norm_data();
        $data = $event->get_data() ?? [];
        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new Unexpected_Type_Exception($data, 'array or (\Traversable and \ArrayAccess)');
        }
        if (null !== $data_to_merge_into && !\is_array($data_to_merge_into) && !($data_to_merge_into instanceof \Traversable && $data_to_merge_into instanceof \ArrayAccess)) {
            throw new Unexpected_Type_Exception($data_to_merge_into, 'array or (\Traversable and \ArrayAccess)');
        }
        // If we are not allowed to change anything, return immediately
        if ($data === $data_to_merge_into || !$this->allow_add && !$this->allow_delete) {
            $event->set_data($data_to_merge_into);
            return;
        }
        if (null === $data_to_merge_into) {
            // No original data was set. Set it if allowed
            if ($this->allow_add) {
                $data_to_merge_into = $data;
            }
        } else {
            // Calculate delta
            $items_to_add = \is_object($data) ? clone $data : $data;
            $items_to_delete = [];
            foreach ($data_to_merge_into as $before_key => $before_item) {
                foreach ($data as $after_key => $after_item) {
                    if ($after_item === $before_item) {
                        // Item found, next original item
                        unset($items_to_add[$after_key]);
                        continue 2;
                    }
                }
                // Item not found, remember for deletion
                $items_to_delete[] = $before_key;
            }
            // Remove deleted items before adding to free keys that are to be
            // replaced
            if ($this->allow_delete) {
                foreach ($items_to_delete as $key) {
                    unset($data_to_merge_into[$key]);
                }
            }
            // Add remaining items
            if ($this->allow_add) {
                foreach ($items_to_add as $key => $item) {
                    if (!isset($data_to_merge_into[$key])) {
                        $data_to_merge_into[$key] = $item;
                    } else {
                        $data_to_merge_into[] = $item;
                    }
                }
            }
        }
        $event->set_data($data_to_merge_into);
    }
}