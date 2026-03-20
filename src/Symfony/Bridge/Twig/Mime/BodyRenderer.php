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
namespace Symfony\Bridge\Twig\Mime;

use League\Html_To_Markdown\Html_Converter_Interface;
use Symfony\Component\Mime\Body_Renderer_Interface;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Html_To_Text_Converter\Default_Html_To_Text_Converter;
use Symfony\Component\Mime\Html_To_Text_Converter\Html_To_Text_Converter_Interface;
use Symfony\Component\Mime\Html_To_Text_Converter\League_Html_To_Markdown_Converter;
use Symfony\Component\Mime\Message;
use Symfony\Component\Translation\Locale_Switcher;
use Twig\Environment;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final readonly class Body_Renderer implements Body_Renderer_Interface
{
    private Html_To_Text_Converter_Interface $converter;
    public function __construct(private Environment $twig, private array $context = [], ?Html_To_Text_Converter_Interface $converter = null, private ?Locale_Switcher $locale_switcher = null)
    {
        $this->converter = $converter ?: (interface_exists(Html_Converter_Interface::class) ? new League_Html_To_Markdown_Converter() : new Default_Html_To_Text_Converter());
    }
    public function render(Message $message): void
    {
        if (!$message instanceof Templated_Email) {
            return;
        }
        if ($message->is_rendered()) {
            // email has already been rendered
            return;
        }
        $callback = function () use ($message): void {
            $message_context = $message->get_context();
            if (isset($message_context['email'])) {
                throw new InvalidArgumentException(\sprintf('A "%s" context cannot have an "email" entry as this is a reserved variable.', get_debug_type($message)));
            }
            $vars = array_merge($this->context, $message_context, ['email' => new Wrapped_Templated_Email($this->twig, $message)]);
            if ($template = $message->get_text_template()) {
                $message->text($this->twig->render($template, $vars));
            }
            if ($template = $message->get_html_template()) {
                $message->html($this->twig->render($template, $vars));
            }
            $message->mark_as_rendered();
            // if text body is empty, compute one from the HTML body
            if (!$message->get_text_body() && null !== $html = $message->get_html_body()) {
                $text = $this->converter->convert(\is_resource($html) ? stream_get_contents($html) : $html, $message->get_html_charset());
                $message->text($text, $message->get_html_charset());
            }
        };
        $locale = $message->get_locale();
        if ($locale && $this->locale_switcher) {
            $this->locale_switcher->run_with_locale($locale, $callback);
            return;
        }
        $callback();
    }
}