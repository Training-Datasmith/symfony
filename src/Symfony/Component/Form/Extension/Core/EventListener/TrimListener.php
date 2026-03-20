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
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Util\String_Util;
/**
 * Trims string data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Trim_Listener implements Event_Subscriber_Interface
{
    public function pre_submit(Form_Event $event): void
    {
        $data = $event->get_data();
        if (!\is_string($data)) {
            return;
        }
        $event->set_data(String_Util::trim($data));
    }
    public static function get_subscribed_events(): array
    {
        return [Form_Events::PRE_SUBMIT => 'preSubmit'];
    }
}