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

use Symfony\Component\Mime\Email;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Templated_Email extends Email
{
    private ?string $html_template = null;
    private ?string $text_template = null;
    private ?string $locale = null;
    private array $context = [];
    /**
     * @return $this
     */
    public function text_template(?string $template): static
    {
        $this->text_template = $template;
        return $this;
    }
    /**
     * @return $this
     */
    public function html_template(?string $template): static
    {
        $this->html_template = $template;
        return $this;
    }
    /**
     * @return $this
     */
    public function locale(?string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }
    public function get_text_template(): ?string
    {
        return $this->text_template;
    }
    public function get_html_template(): ?string
    {
        return $this->html_template;
    }
    public function get_locale(): ?string
    {
        return $this->locale;
    }
    /**
     * @return $this
     */
    public function context(array $context): static
    {
        $this->context = $context;
        return $this;
    }
    public function get_context(): array
    {
        return $this->context;
    }
    public function is_rendered(): bool
    {
        return null === $this->html_template && null === $this->text_template;
    }
    public function mark_as_rendered(): void
    {
        $this->text_template = null;
        $this->html_template = null;
        $this->context = [];
    }
    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->html_template, $this->text_template, $this->context, parent::__serialize(), $this->locale];
    }
    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [$this->html_template, $this->text_template, $this->context, $parent_data] = $data;
        $this->locale = $data[4] ?? null;
        parent::__unserialize($parent_data);
    }
}