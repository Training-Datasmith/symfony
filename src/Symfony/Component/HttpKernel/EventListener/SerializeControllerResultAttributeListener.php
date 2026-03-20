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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Attribute\Serialize;
use Symfony\Component\Http_Kernel\Event\Controller_Attribute_Event;
use Symfony\Component\Http_Kernel\Event\View_Event;
use Symfony\Component\Http_Kernel\Exception\Unsupported_Media_Type_Http_Exception;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Serializer\Exception\Unsupported_Format_Exception;
use Symfony\Component\Serializer\Serializer_Interface;
/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final readonly class Serialize_Controller_Result_Attribute_Listener implements Event_Subscriber_Interface
{
    public function __construct(private ?Serializer_Interface $serializer)
    {
    }
    /**
     * @param ControllerAttributeEvent<Serialize> $event
     */
    public function on_view(Controller_Attribute_Event $event): void
    {
        $kernel_event = $event->kernel_event;
        if (!$kernel_event instanceof View_Event) {
            return;
        }
        if (!$this->serializer) {
            throw new \LogicException(\sprintf('The "symfony/serializer" component is required to use the "#[%s]" attribute. Try running "composer require symfony/serializer".', Serialize::class));
        }
        $request = $kernel_event->get_request();
        $controller_result = $kernel_event->get_controller_result();
        $format = $request->get_request_format('json');
        try {
            $data = $this->serializer->serialize($controller_result, $format, $event->attribute->context);
        } catch (Unsupported_Format_Exception $exception) {
            throw new Unsupported_Media_Type_Http_Exception(\sprintf('Unsupported format "%s".', $format), $exception->get_previous());
        }
        $headers = $this->merge_headers($event->attribute, $request, $format);
        $response = new Response($data, $event->attribute->code, $headers);
        $kernel_event->set_response($response);
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::VIEW . '.' . Serialize::class => 'onView'];
    }
    /**
     * @return array<string, scalar>
     */
    private function merge_headers(Serialize $attribute, Request $request, string $format): array
    {
        $headers = array_combine(array_map(strtolower(...), array_keys($attribute->headers)), array_values($attribute->headers));
        if (!isset($headers['content-type'])) {
            $headers['content-type'] = $request->get_mime_type($format);
        }
        return $headers;
    }
}