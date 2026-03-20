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
/**
 * Adds a protocol to a URL if it doesn't already have one.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Fix_Url_Protocol_Listener implements Event_Subscriber_Interface
{
    /**
     * @param string|null $defaultProtocol The URL scheme to add when there is none or null to not modify the data
     */
    public function __construct(private readonly ?string $default_protocol = 'http')
    {
    }
    public function on_submit(Form_Event $event): void
    {
        $data = $event->get_data();
        if ($this->default_protocol && $data && \is_string($data) && !preg_match('~^(?:[/.]|[\w+.-]+://|[^:/?@#]++@)~', $data)) {
            $event->set_data($this->default_protocol . '://' . $data);
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Form_Events::SUBMIT => 'onSubmit'];
    }
}