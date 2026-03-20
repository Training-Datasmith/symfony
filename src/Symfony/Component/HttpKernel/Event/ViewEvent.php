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
namespace Symfony\Component\Http_Kernel\Event;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
/**
 * Allows to create a response for the return value of a controller.
 *
 * Call setResponse() to set the response that will be returned for the
 * current request. The propagation of this event is stopped as soon as a
 * response is set.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class View_Event extends Request_Event
{
    public readonly ?Controller_Arguments_Metadata $controller_metadata;
    /**
     * @deprecated since Symfony 8.1, use $controllerMetadata instead
     */
    public private(set) ?Controller_Arguments_Event $controller_arguments_event {
        get {
            trigger_deprecation('symfony/http-kernel', '8.1', 'Accessing the "controllerArgumentsEvent" property of the "%s" class is deprecated. Use "controllerMetadata" instead.', self::class);
            if (!$m = $this->controller_metadata) {
                return null;
            }
            return $this->controller_arguments_event ??= new Controller_Arguments_Event($this->get_kernel(), \Closure::bind(fn() => $this->controller_event, $m, Controller_Metadata::class)(), $m->get_arguments(), $this->get_request(), $this->get_request_type());
        }
    }
    public function __construct(Http_Kernel_Interface $kernel, Request $request, int $request_type, private mixed $controller_result, Controller_Arguments_Metadata|Controller_Arguments_Event|null $controller_metadata = null)
    {
        if ($controller_metadata instanceof Controller_Arguments_Event) {
            trigger_deprecation('symfony/http-kernel', '8.1', 'Passing a ControllerArgumentsEvent to the ViewEvent constructor is deprecated. Pass a ControllerArgumentsMetadata instance instead.');
            $this->controller_arguments_event = $controller_metadata;
            $controller_event = \Closure::bind(fn(): \Symfony\Component\Http_Kernel\Event\Controller_Event => $this->controller_event, $controller_metadata, Controller_Arguments_Event::class)();
            $controller_metadata = new Controller_Arguments_Metadata($controller_event, $controller_metadata);
        }
        $this->controller_metadata = $controller_metadata;
        parent::__construct($kernel, $request, $request_type);
    }
    public function get_controller_result(): mixed
    {
        return $this->controller_result;
    }
    public function set_controller_result(mixed $controller_result): void
    {
        $this->controller_result = $controller_result;
    }
}