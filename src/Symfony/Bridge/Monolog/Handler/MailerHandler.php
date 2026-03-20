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
namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Html_Formatter;
use Monolog\Formatter\Line_Formatter;
use Monolog\Handler\Abstract_Processing_Handler;
use Monolog\Level;
use Monolog\Log_Record;
use Symfony\Component\Mailer\Mailer_Interface;
use Symfony\Component\Mime\Email;
/**
 * @author Alexander Borisov <boshurik@gmail.com>
 */
final class Mailer_Handler extends Abstract_Processing_Handler
{
    private readonly \Closure|Email $message_template;
    public function __construct(private readonly Mailer_Interface $mailer, callable|Email $message_template, string|int|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->message_template = $message_template instanceof Email ? $message_template : $message_template(...);
    }
    public function handle_batch(array $records): void
    {
        $messages = [];
        foreach ($records as $record) {
            if ($record->level->is_lower_than($this->level)) {
                continue;
            }
            $messages[] = $this->process_record($record);
        }
        if ($messages) {
            $this->send((string) $this->get_formatter()->format_batch($messages), $messages);
        }
    }
    protected function write(Log_Record $record): void
    {
        $this->send((string) $record->formatted, [$record]);
    }
    /**
     * Send a mail with the given content.
     *
     * @param string $content formatted email body to be sent
     * @param array  $records the array of log records that formed this content
     */
    protected function send(string $content, array $records): void
    {
        $this->mailer->send($this->build_message($content, $records));
    }
    /**
     * Gets the formatter for the Message subject.
     *
     * @param string $format The format of the subject
     */
    protected function get_subject_formatter(string $format): Formatter_Interface
    {
        return new Line_Formatter($format);
    }
    /**
     * Creates instance of Message to be sent.
     *
     * @param string $content formatted email body to be sent
     * @param array  $records Log records that formed the content
     */
    protected function build_message(string $content, array $records): Email
    {
        if ($this->message_template instanceof Email) {
            $message = clone $this->message_template;
        } elseif (\is_callable($this->message_template)) {
            $message = ($this->message_template)($content, $records);
            if (!$message instanceof Email) {
                throw new \InvalidArgumentException(\sprintf('Could not resolve message from a callable. Instance of "%s" is expected.', Email::class));
            }
        } else {
            throw new \InvalidArgumentException('Could not resolve message as instance of Email or a callable returning it.');
        }
        if ($records) {
            $subject_formatter = $this->get_subject_formatter($message->get_subject());
            $message->subject($subject_formatter->format($this->get_highest_record($records)));
        }
        if ($this->get_formatter() instanceof Html_Formatter) {
            if ($message->get_html_charset()) {
                $message->html($content, $message->get_html_charset());
            } else {
                $message->html($content);
            }
        } else if ($message->get_text_charset()) {
            $message->text($content, $message->get_text_charset());
        } else {
            $message->text($content);
        }
        return $message;
    }
    protected function get_highest_record(array $records): Log_Record
    {
        $highest_record = null;
        foreach ($records as $record) {
            if (null === $highest_record || $highest_record->level->is_lower_than($record->level)) {
                $highest_record = $record;
            }
        }
        return $highest_record;
    }
}