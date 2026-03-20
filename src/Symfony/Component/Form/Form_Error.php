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
namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Translation\Translator;
/**
 * Wraps errors in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Error
{
    protected string $message_template;
    /**
     * The form that spawned this error.
     */
    private ?Form_Interface $origin = null;
    /**
     * Any array key in $messageParameters will be used as a placeholder in
     * $messageTemplate.
     *
     * @param string      $message              The translated error message
     * @param string|null $messageTemplate      The template for the error message
     * @param array       $messageParameters    The parameters that should be
     *                                          substituted in the message template
     * @param int|null    $messagePluralization The value for error message pluralization
     * @param mixed       $cause                The cause of the error
     *
     * @see Translator
     */
    public function __construct(private readonly string $message, ?string $message_template = null, protected array $message_parameters = [], protected ?int $message_pluralization = null, private readonly mixed $cause = null)
    {
        $this->message_template = $message_template ?: $message;
    }
    /**
     * Returns the error message.
     */
    public function get_message(): string
    {
        return $this->message;
    }
    /**
     * Returns the error message template.
     */
    public function get_message_template(): string
    {
        return $this->message_template;
    }
    /**
     * Returns the parameters to be inserted in the message template.
     */
    public function get_message_parameters(): array
    {
        return $this->message_parameters;
    }
    /**
     * Returns the value for error message pluralization.
     */
    public function get_message_pluralization(): ?int
    {
        return $this->message_pluralization;
    }
    /**
     * Returns the cause of this error.
     */
    public function get_cause(): mixed
    {
        return $this->cause;
    }
    /**
     * Sets the form that caused this error.
     *
     * This method must only be called once.
     *
     * @throws BadMethodCallException If the method is called more than once
     */
    public function set_origin(Form_Interface $origin): void
    {
        if (null !== $this->origin) {
            throw new BadMethodCallException('setOrigin() must only be called once.');
        }
        $this->origin = $origin;
    }
    /**
     * Returns the form that caused this error.
     */
    public function get_origin(): ?Form_Interface
    {
        return $this->origin;
    }
}