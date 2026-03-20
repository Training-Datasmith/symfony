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

use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\Abstract_Part;
use Symfony\Component\Mime\Part\Data_Part;
use Twig\Extra\Css_Inliner\Css_Inliner_Extension;
use Twig\Extra\Inky\Inky_Extension;
use Twig\Extra\Markdown\Markdown_Extension;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Notification_Email extends Templated_Email
{
    public const IMPORTANCE_URGENT = 'urgent';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_MEDIUM = 'medium';
    public const IMPORTANCE_LOW = 'low';
    private string $theme = 'default';
    private array $context = ['importance' => self::IMPORTANCE_LOW, 'content' => '', 'exception' => false, 'action_text' => null, 'action_url' => null, 'markdown' => false, 'raw' => false, 'footer_text' => 'Notification email sent by Symfony'];
    private bool $rendered = false;
    public function __construct(?Headers $headers = null, ?Abstract_Part $body = null)
    {
        $missing_packages = [];
        if (!class_exists(Css_Inliner_Extension::class)) {
            $missing_packages['twig/cssinliner-extra'] = 'CSS Inliner';
        }
        if (!class_exists(Inky_Extension::class)) {
            $missing_packages['twig/inky-extra'] = 'Inky';
        }
        if ($missing_packages) {
            throw new \LogicException(\sprintf('You cannot use "%s" if the "%s" Twig extension%s not available. Try running "%s".', static::class, implode('" and "', $missing_packages), \count($missing_packages) > 1 ? 's are' : ' is', 'composer require ' . implode(' ', array_keys($missing_packages))));
        }
        parent::__construct($headers, $body);
    }
    /**
     * Creates a NotificationEmail instance that is appropriate to send to normal (non-admin) users.
     */
    public static function as_public_email(?Headers $headers = null, ?Abstract_Part $body = null): self
    {
        $email = new static($headers, $body);
        $email->mark_as_public();
        return $email;
    }
    /**
     * @return $this
     */
    public function mark_as_public(): static
    {
        $this->context['importance'] = null;
        $this->context['footer_text'] = null;
        return $this;
    }
    /**
     * @return $this
     */
    public function markdown(string $content): static
    {
        if (!class_exists(Markdown_Extension::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" if the Markdown Twig extension is not available. Try running "composer require twig/markdown-extra".', __METHOD__));
        }
        $this->context['markdown'] = true;
        return $this->content($content);
    }
    /**
     * @return $this
     */
    public function content(string $content, bool $raw = false): static
    {
        $this->context['content'] = $content;
        $this->context['raw'] = $raw;
        return $this;
    }
    /**
     * @return $this
     */
    public function action(string $text, string $url): static
    {
        $this->context['action_text'] = $text;
        $this->context['action_url'] = $url;
        return $this;
    }
    /**
     * @return $this
     */
    public function importance(string $importance): static
    {
        $this->context['importance'] = $importance;
        return $this;
    }
    /**
     * @return $this
     */
    public function exception(\Throwable|Flatten_Exception $exception): static
    {
        $exception_as_string = $this->get_exception_as_string($exception);
        $this->context['exception'] = true;
        $this->add_part(new Data_Part($exception_as_string, 'exception.txt', 'text/plain'));
        $this->importance(self::IMPORTANCE_URGENT);
        if (!$this->get_subject()) {
            $this->subject($exception->get_message());
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function theme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }
    public function get_text_template(): ?string
    {
        if ($template = parent::get_text_template()) {
            return $template;
        }
        return '@email/' . $this->theme . '/notification/body.txt.twig';
    }
    public function get_html_template(): ?string
    {
        if ($template = parent::get_html_template()) {
            return $template;
        }
        return '@email/' . $this->theme . '/notification/body.html.twig';
    }
    /**
     * @return $this
     */
    public function context(array $context): static
    {
        $parent_context = [];
        foreach ($context as $key => $value) {
            if (\array_key_exists($key, $this->context)) {
                $this->context[$key] = $value;
            } else {
                $parent_context[$key] = $value;
            }
        }
        parent::context($parent_context);
        return $this;
    }
    public function get_context(): array
    {
        return array_merge($this->context, parent::get_context());
    }
    public function is_rendered(): bool
    {
        return $this->rendered;
    }
    public function mark_as_rendered(): void
    {
        parent::mark_as_rendered();
        $this->rendered = true;
    }
    public function get_prepared_headers(): Headers
    {
        $headers = parent::get_prepared_headers();
        $importance = $this->context['importance'] ?? self::IMPORTANCE_LOW;
        $this->priority($this->determine_priority($importance));
        if ($this->context['importance']) {
            $headers->set_header_body('Text', 'Subject', \sprintf('[%s] %s', strtoupper($importance), $this->get_subject()));
        }
        return $headers;
    }
    private function determine_priority(string $importance): int
    {
        return match ($importance) {
            self::IMPORTANCE_URGENT => self::PRIORITY_HIGHEST,
            self::IMPORTANCE_HIGH => self::PRIORITY_HIGH,
            self::IMPORTANCE_MEDIUM => self::PRIORITY_NORMAL,
            default => self::PRIORITY_LOW,
        };
    }
    private function get_exception_as_string(\Throwable|Flatten_Exception $exception): string
    {
        if (class_exists(Flatten_Exception::class)) {
            $exception = $exception instanceof Flatten_Exception ? $exception : Flatten_Exception::create_from_throwable($exception);
            return $exception->get_as_string();
        }
        $message = $exception::class;
        if ('' !== $exception->get_message()) {
            $message .= ': ' . $exception->get_message();
        }
        $message .= ' in ' . $exception->get_file() . ':' . $exception->get_line() . "\n";
        $message .= "Stack trace:\n" . $exception->get_trace_as_string() . "\n\n";
        return rtrim($message);
    }
    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->context, $this->theme, $this->rendered, parent::__serialize()];
    }
    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        if (4 === \count($data)) {
            [$this->context, $this->theme, $this->rendered, $parent_data] = $data;
        } elseif (3 === \count($data)) {
            [$this->context, $this->theme, $parent_data] = $data;
        } else {
            // Backwards compatibility for deserializing data structures that were serialized without the theme
            [$this->context, $parent_data] = $data;
        }
        parent::__unserialize($parent_data);
    }
}