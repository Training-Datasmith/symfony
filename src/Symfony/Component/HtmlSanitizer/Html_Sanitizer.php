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
namespace Symfony\Component\Html_Sanitizer;

use Symfony\Component\Html_Sanitizer\Parser\Native_Parser;
use Symfony\Component\Html_Sanitizer\Parser\Parser_Interface;
use Symfony\Component\Html_Sanitizer\Reference\W3c_Reference;
use Symfony\Component\Html_Sanitizer\Text_Sanitizer\String_Sanitizer;
use Symfony\Component\Html_Sanitizer\Visitor\Dom_Visitor;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Html_Sanitizer implements Html_Sanitizer_Interface
{
    /**
     * @var array<string, DomVisitor>
     */
    private array $dom_visitors = [];
    public function __construct(private readonly Html_Sanitizer_Config $config, private readonly ?Parser_Interface $parser = new Native_Parser())
    {
    }
    public function sanitize(string $input): string
    {
        return $this->sanitize_for(W3c_Reference::CONTEXT_BODY, $input);
    }
    public function sanitize_for(string $element, string $input): string
    {
        $element = String_Sanitizer::html_lower($element);
        $context = W3c_Reference::CONTEXTS_MAP[$element] ?? W3c_Reference::CONTEXT_BODY;
        $element = isset(W3c_Reference::BODY_ELEMENTS[$element]) ? $element : $context;
        // Text context: early return with HTML encoding
        if (W3c_Reference::CONTEXT_TEXT === $context) {
            return String_Sanitizer::encode_html_entities($input);
        }
        // Other context: build a DOM visitor
        $this->dom_visitors[$context] ??= $this->create_dom_visitor_for_context($context);
        // Prevent DOS attack induced by extremely long HTML strings
        if (-1 !== $this->config->get_max_input_length() && \strlen($input) > $this->config->get_max_input_length()) {
            $input = substr($input, 0, $this->config->get_max_input_length());
        }
        // Only operate on valid UTF-8 strings. This is necessary to prevent cross
        // site scripting issues on Internet Explorer 6. Idea from Drupal (filter_xss).
        if (!$this->is_valid_utf8($input)) {
            return '';
        }
        // Remove NULL character and HTML entities for null byte
        $input = str_replace(\chr(0), '�', $input);
        // Parse as HTML
        if ('' === trim($input) || !$parsed = $this->parser->parse($input, $element)) {
            return '';
        }
        // Visit the DOM tree and render the sanitized nodes
        return $this->dom_visitors[$context]->visit($parsed)?->render() ?? '';
    }
    private function is_valid_utf8(string $html): bool
    {
        // preg_match() fails silently on strings containing invalid UTF-8.
        return '' === $html || preg_match('//u', $html);
    }
    private function create_dom_visitor_for_context(string $context): Dom_Visitor
    {
        $elements_config = [];
        // Head: only a few elements are allowed
        if (W3c_Reference::CONTEXT_HEAD === $context) {
            foreach ($this->config->get_allowed_elements() as $allowed_element => $allowed_attributes) {
                if (\array_key_exists($allowed_element, W3c_Reference::HEAD_ELEMENTS)) {
                    $elements_config[$allowed_element] = $allowed_attributes;
                }
            }
            foreach ($this->config->get_blocked_elements() as $blocked_element => $v) {
                if (\array_key_exists($blocked_element, W3c_Reference::HEAD_ELEMENTS)) {
                    $elements_config[$blocked_element] = Html_Sanitizer_Action::Block;
                }
            }
            foreach ($this->config->get_dropped_elements() as $dropped_element => $v) {
                if (\array_key_exists($dropped_element, W3c_Reference::HEAD_ELEMENTS)) {
                    $elements_config[$dropped_element] = Html_Sanitizer_Action::Drop;
                }
            }
            return new Dom_Visitor($this->config, $elements_config);
        }
        // Body: allow any configured element that isn't in <head>
        foreach ($this->config->get_allowed_elements() as $allowed_element => $allowed_attributes) {
            if (!\array_key_exists($allowed_element, W3c_Reference::HEAD_ELEMENTS)) {
                $elements_config[$allowed_element] = $allowed_attributes;
            }
        }
        foreach ($this->config->get_blocked_elements() as $blocked_element => $v) {
            if (!\array_key_exists($blocked_element, W3c_Reference::HEAD_ELEMENTS)) {
                $elements_config[$blocked_element] = Html_Sanitizer_Action::Block;
            }
        }
        foreach ($this->config->get_dropped_elements() as $dropped_element => $v) {
            if (!\array_key_exists($dropped_element, W3c_Reference::HEAD_ELEMENTS)) {
                $elements_config[$dropped_element] = Html_Sanitizer_Action::Drop;
            }
        }
        return new Dom_Visitor($this->config, $elements_config);
    }
}