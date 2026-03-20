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
namespace Symfony\Component\Html_Sanitizer\Visitor\Node;

use Symfony\Component\Html_Sanitizer\Text_Sanitizer\String_Sanitizer;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Node implements Node_Interface
{
    // HTML5 elements which are self-closing
    private const VOID_ELEMENTS = ['area' => true, 'base' => true, 'br' => true, 'col' => true, 'embed' => true, 'hr' => true, 'img' => true, 'input' => true, 'keygen' => true, 'link' => true, 'meta' => true, 'param' => true, 'source' => true, 'track' => true, 'wbr' => true];
    private array $attributes = [];
    private array $children = [];
    public function __construct(private readonly Node_Interface $parent, private readonly string $tag_name)
    {
    }
    public function get_parent(): \Symfony\Component\Html_Sanitizer\Visitor\Node\Node_Interface
    {
        return $this->parent;
    }
    public function get_attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }
    public function set_attribute(string $name, ?string $value, bool $override = false): void
    {
        // Always use only the first declaration (ease sanitization)
        if ($override || !\array_key_exists($name, $this->attributes)) {
            $this->attributes[$name] = $value;
        }
    }
    public function add_child(Node_Interface $node): void
    {
        $this->children[] = $node;
    }
    public function render(): string
    {
        if (isset(self::VOID_ELEMENTS[$this->tag_name])) {
            return '<' . $this->tag_name . $this->render_attributes() . ' />';
        }
        $rendered = '<' . $this->tag_name . $this->render_attributes() . '>';
        foreach ($this->children as $child) {
            $rendered .= $child->render();
        }
        return $rendered . '</' . $this->tag_name . '>';
    }
    private function render_attributes(): string
    {
        $rendered = [];
        foreach ($this->attributes as $name => $value) {
            if (null === $value) {
                // Tag should be removed as a sanitizer found suspect data inside
                continue;
            }
            $attr = String_Sanitizer::encode_html_entities($name);
            if ('' !== $value) {
                // In quirks mode, IE8 does a poor job producing innerHTML values.
                // If JavaScript does:
                //      nodeA.innerHTML = nodeB.innerHTML;
                // and nodeB contains (or even if ` was encoded properly):
                //      <div attr="``foo=bar">
                // then IE8 will produce:
                //      <div attr=``foo=bar>
                // as the value of nodeB.innerHTML and assign it to nodeA.
                // IE8's HTML parser treats `` as a blank attribute value and foo=bar becomes a separate attribute.
                // Adding a space at the end of the attribute prevents this by forcing IE8 to put double
                // quotes around the attribute when computing nodeB.innerHTML.
                if (str_contains((string) $value, '`')) {
                    $value .= ' ';
                }
                $attr .= '="' . String_Sanitizer::encode_html_entities($value) . '"';
            }
            $rendered[] = $attr;
        }
        return $rendered ? ' ' . implode(' ', $rendered) : '';
    }
}