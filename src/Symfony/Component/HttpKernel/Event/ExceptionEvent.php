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
 * Allows to create a response for a thrown exception.
 *
 * Call setResponse() to set the response that will be returned for the
 * current request. The propagation of this event is stopped as soon as a
 * response is set.
 *
 * You can also call setThrowable() to replace the thrown exception. This
 * exception will be thrown if no response is set during processing of this
 * event.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Exception_Event extends Request_Event
{
    private \Throwable $throwable;
    private bool $allow_custom_response_code = false;
    public function __construct(Http_Kernel_Interface $kernel, Request $request, int $request_type, \Throwable $e, private readonly bool $is_kernel_terminating = false, public readonly ?Controller_Metadata $controller_metadata = null)
    {
        parent::__construct($kernel, $request, $request_type);
        $this->set_throwable($e);
    }
    public function get_throwable(): \Throwable
    {
        return $this->throwable;
    }
    /**
     * Replaces the thrown exception.
     *
     * This exception will be thrown if no response is set in the event.
     */
    public function set_throwable(\Throwable $exception): void
    {
        $this->throwable = $exception;
    }
    /**
     * Mark the event as allowing a custom response code.
     */
    public function allow_custom_response_code(): void
    {
        $this->allow_custom_response_code = true;
    }
    /**
     * Returns true if the event allows a custom response code.
     */
    public function is_allowing_custom_response_code(): bool
    {
        return $this->allow_custom_response_code;
    }
    public function is_kernel_terminating(): bool
    {
        return $this->is_kernel_terminating;
    }
}