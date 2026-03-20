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
use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Base class for events dispatched in the HttpKernel component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Kernel_Event extends Event
{
    /**
     * @param int $requestType The request type the kernel is currently processing; one of
     *                         HttpKernelInterface::MAIN_REQUEST or HttpKernelInterface::SUB_REQUEST
     */
    public function __construct(private readonly Http_Kernel_Interface $kernel, private readonly Request $request, private readonly ?int $request_type)
    {
    }
    /**
     * Returns the kernel in which this event was thrown.
     */
    public function get_kernel(): Http_Kernel_Interface
    {
        return $this->kernel;
    }
    /**
     * Returns the request the kernel is currently processing.
     */
    public function get_request(): Request
    {
        return $this->request;
    }
    /**
     * Returns the request type the kernel is currently processing.
     *
     * @return int One of HttpKernelInterface::MAIN_REQUEST and
     *             HttpKernelInterface::SUB_REQUEST
     */
    public function get_request_type(): int
    {
        return $this->request_type;
    }
    /**
     * Checks if this is the main request.
     */
    public function is_main_request(): bool
    {
        return Http_Kernel_Interface::MAIN_REQUEST === $this->request_type;
    }
}