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
namespace Symfony\Component\Form\Exception;

/**
 * Indicates a value transformation error.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Transformation_Failed_Exception extends RuntimeException
{
    private ?string $invalid_message;
    private array $invalid_message_parameters;
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?string $invalid_message = null, array $invalid_message_parameters = [])
    {
        parent::__construct($message, $code, $previous);
        $this->set_invalid_message($invalid_message, $invalid_message_parameters);
    }
    /**
     * Sets the message that will be shown to the user.
     *
     * @param string|null $invalidMessage           The message or message key
     * @param array       $invalidMessageParameters Data to be passed into the translator
     */
    public function set_invalid_message(?string $invalid_message, array $invalid_message_parameters = []): void
    {
        $this->invalid_message = $invalid_message;
        $this->invalid_message_parameters = $invalid_message_parameters;
    }
    public function get_invalid_message(): ?string
    {
        return $this->invalid_message;
    }
    public function get_invalid_message_parameters(): array
    {
        return $this->invalid_message_parameters;
    }
}