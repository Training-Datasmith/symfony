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
namespace Symfony\Component\Dependency_Injection\Exception;

/**
 * Thrown when a definition cannot be autowired.
 */
class Autowiring_Failed_Exception extends RuntimeException
{
    private ?\Closure $message_callback = null;
    public function __construct(private readonly string $service_id, string|\Closure $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        if ($message instanceof \Closure && \function_exists('xdebug_is_enabled') && xdebug_is_enabled()) {
            $message = $message();
        }
        if (!$message instanceof \Closure) {
            parent::__construct($message, $code, $previous);
            return;
        }
        $this->message_callback = $message;
        parent::__construct('', $code, $previous);
        $this->message = new class($this->message, $this->message_callback)
        {
            private string|self $message;
            private ?\Closure $message_callback = null;
            public function __construct(&$message, &$message_callback)
            {
                $this->message =& $message;
                $this->message_callback =& $message_callback;
            }
            public function __toString(): string
            {
                $message_callback = $this->message_callback;
                $this->message_callback = null;
                try {
                    return $this->message = $message_callback();
                } catch (\Throwable $e) {
                    return $this->message = $e->get_message();
                }
            }
        };
    }
    public function get_message_callback(): ?\Closure
    {
        return $this->message_callback;
    }
    public function get_service_id(): string
    {
        return $this->service_id;
    }
}