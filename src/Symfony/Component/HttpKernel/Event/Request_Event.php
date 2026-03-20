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

use Symfony\Component\Http_Foundation\Response;
/**
 * Allows to create a response for a request.
 *
 * Call setResponse() to set the response that will be returned for the
 * current request. The propagation of this event is stopped as soon as a
 * response is set.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Request_Event extends Kernel_Event
{
    private ?Response $response = null;
    /**
     * Returns the response object.
     */
    public function get_response(): ?Response
    {
        return $this->response;
    }
    /**
     * Sets a response and stops event propagation.
     */
    public function set_response(Response $response): void
    {
        $this->response = $response;
        $this->stop_propagation();
    }
    /**
     * Returns whether a response was set.
     *
     * @psalm-assert-if-true !null $this->getResponse()
     */
    public function has_response(): bool
    {
        return null !== $this->response;
    }
}