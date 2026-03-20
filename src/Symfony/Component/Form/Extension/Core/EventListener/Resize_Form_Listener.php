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
use Symfony\Component\Form\Event\Post_Set_Data_Event;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Form_Interface;
/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Resize_Form_Listener implements Event_Subscriber_Interface
{
    protected array $prototype_options;
    private readonly \Closure|bool $delete_empty;
    public function __construct(private readonly string $type, private readonly array $options = [], private readonly bool $allow_add = false, private readonly bool $allow_delete = false, bool|callable $delete_empty = false, ?array $prototype_options = null, private readonly bool $keep_as_list = false)
    {
        $this->delete_empty = \is_bool($delete_empty) ? $delete_empty : $delete_empty(...);
        $this->prototype_options = $prototype_options ?? $options;
    }
    public static function get_subscribed_events(): array
    {
        return [
            Form_Events::POST_SET_DATA => ['postSetData', 255],
            // as early as possible
            Form_Events::PRE_SUBMIT => 'preSubmit',
            // (MergeCollectionListener, MergeDoctrineCollectionListener)
            Form_Events::SUBMIT => ['onSubmit', 50],
        ];
    }
    final public function post_set_data(Post_Set_Data_Event $event): void
    {
        $form = $event->get_form();
        $data = $event->get_data() ?? [];
        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new Unexpected_Type_Exception($data, 'array or (\Traversable and \ArrayAccess)');
        }
        // First remove all rows
        foreach ($form as $name => $child) {
            $form->remove($name);
        }
        // Then add all rows again in the correct order
        foreach ($data as $name => $value) {
            $form->add($name, $this->type, array_replace(['property_path' => '[' . $name . ']'], $this->options));
        }
    }
    public function pre_submit(Form_Event $event): void
    {
        $form = $event->get_form();
        $data = $event->get_data();
        if (!\is_array($data)) {
            $data = [];
        }
        // Remove all empty rows
        if ($this->allow_delete) {
            foreach ($form as $name => $child) {
                if (!isset($data[$name])) {
                    $form->remove($name);
                }
            }
        }
        // Add all additional rows
        if ($this->allow_add) {
            foreach ($data as $name => $value) {
                if (!$form->has($name)) {
                    $form->add($name, $this->type, array_replace(['property_path' => '[' . $name . ']'], $this->prototype_options));
                }
            }
        }
    }
    public function on_submit(Form_Event $event): void
    {
        $form = $event->get_form();
        $data = $event->get_data() ?? [];
        // At this point, $data is an array or an array-like object that already contains the
        // new entries, which were added by the data mapper. The data mapper ignores existing
        // entries, so we need to manually unset removed entries in the collection.
        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new Unexpected_Type_Exception($data, 'array or (\Traversable and \ArrayAccess)');
        }
        if ($this->delete_empty) {
            $previous_data = $form->get_data();
            /** @var FormInterface $child */
            foreach ($form as $name => $child) {
                if (!$child->is_valid()) {
                    continue;
                }
                if (!$child->is_synchronized()) {
                    continue;
                }
                $is_new = !isset($previous_data[$name]);
                $is_empty = \is_callable($this->delete_empty) ? ($this->delete_empty)($child->get_data()) : $child->is_empty();
                // $isNew can only be true if allowAdd is true, so we don't
                // need to check allowAdd again
                if ($is_empty && ($is_new || $this->allow_delete)) {
                    unset($data[$name]);
                    $form->remove($name);
                }
            }
        }
        // The data mapper only adds, but does not remove items, so do this
        // here
        if ($this->allow_delete) {
            $to_delete = [];
            foreach ($data as $name => $child) {
                if (!$form->has($name)) {
                    $to_delete[] = $name;
                }
            }
            foreach ($to_delete as $name) {
                unset($data[$name]);
            }
        }
        if ($this->keep_as_list) {
            $form_reindex = $data_keys = [];
            foreach ($data as $key => $value) {
                $data_keys[] = $key;
            }
            foreach ($data_keys as $key) {
                unset($data[$key]);
            }
            foreach ($form as $name => $child) {
                $form_reindex[] = $child;
                $form->remove($name);
            }
            foreach ($form_reindex as $index => $child) {
                $form->add($index, $this->type, array_replace(['property_path' => '[' . $index . ']'], $this->options, ['data' => $child->get_data()]));
                $data[$index] = $child->get_data();
            }
        }
        $event->set_data($data);
    }
}