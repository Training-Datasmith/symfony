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
namespace Symfony\Component\Dom_Crawler;

use Symfony\Component\Css_Selector\Css_Selector_Converter;
/**
 * Crawler eases navigation of a list of \DOMNode objects.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<int, \DOMNode>
 */
class Crawler implements \Countable, \IteratorAggregate
{
    /**
     * The default namespace prefix to be used with XPath and CSS expressions.
     */
    private string $default_namespace_prefix = 'default';
    /**
     * A map of manually registered namespaces.
     *
     * @var array<string, string>
     */
    private array $namespaces = [];
    /**
     * A map of cached namespaces.
     *
     * @var \ArrayObject<string, string|null>
     */
    private \ArrayObject $cached_namespaces;
    private ?string $base_href;
    private ?\Dom_Document $document = null;
    /**
     * @var list<\DOMNode>
     */
    private array $nodes = [];
    /**
     * Whether the Crawler contains HTML or XML content (used when converting CSS to XPath).
     */
    private bool $is_html = true;
    /**
     * @param \DOMNodeList<\DOMNode>|\DOMNode|\DOMNode[]|string|null $node A Node to use as the base for the crawling
     */
    public function __construct(\Dom_Node_List|\Dom_Node|array|string|null $node = null, protected ?string $uri = null, ?string $base_href = null)
    {
        $this->base_href = $base_href ?: $uri;
        $this->cached_namespaces = new \ArrayObject();
        $this->add($node);
    }
    /**
     * Returns the current URI.
     */
    public function get_uri(): ?string
    {
        return $this->uri;
    }
    /**
     * Returns base href.
     */
    public function get_base_href(): ?string
    {
        return $this->base_href;
    }
    /**
     * Removes all the nodes.
     */
    public function clear(): void
    {
        $this->nodes = [];
        $this->document = null;
        $this->cached_namespaces = new \ArrayObject();
    }
    /**
     * Adds a node to the current list of nodes.
     *
     * This method uses the appropriate specialized add*() method based
     * on the type of the argument.
     *
     * @param \DOMNodeList<\DOMNode>|\DOMNode|\DOMNode[]|string|null $node
     */
    public function add(\Dom_Node_List|\Dom_Node|array|string|null $node): void
    {
        if ($node instanceof \Dom_Node_List) {
            $this->add_node_list($node);
        } elseif ($node instanceof \Dom_Node) {
            $this->add_node($node);
        } elseif (\is_array($node)) {
            $this->add_nodes($node);
        } elseif (\is_string($node)) {
            $this->add_content($node);
        }
    }
    /**
     * Adds HTML/XML content.
     *
     * If the charset is not set via the content type, it is assumed to be UTF-8,
     * or ISO-8859-1 as a fallback, which is the default charset defined by the
     * HTTP 1.1 specification.
     */
    public function add_content(string $content, ?string $type = null): void
    {
        if (!$type) {
            $type = str_starts_with($content, '<?xml') ? 'application/xml' : 'text/html';
        }
        // DOM only for HTML/XML content
        if (!preg_match('/(x|ht)ml/i', $type, $xml_matches)) {
            return;
        }
        $charset = preg_match('//u', $content) ? 'UTF-8' : 'ISO-8859-1';
        // http://www.w3.org/TR/encoding/#encodings
        // http://www.w3.org/TR/REC-xml/#NT-EncName
        $content = preg_replace_callback('/(charset *= *["\']?)([a-zA-Z\-0-9_:.]+)/i', function ($m) use (&$charset): string {
            if ('charset=' === $this->convert_to_html_entities('charset=', $m[2])) {
                $charset = $m[2];
            }
            return $m[1] . $charset;
        }, $content, 1);
        if ('x' === $xml_matches[1]) {
            $this->add_xml_content($content, $charset);
        } else {
            $this->add_html_content($content, $charset);
        }
    }
    /**
     * Adds an HTML content to the list of nodes.
     *
     * The libxml errors are disabled when the content is parsed.
     *
     * If you want to get parsing errors, be sure to enable
     * internal errors via libxml_use_internal_errors(true)
     * and then, get the errors via libxml_get_errors(). Be
     * sure to clear errors with libxml_clear_errors() afterward.
     */
    public function add_html_content(string $content, string $charset = 'UTF-8'): void
    {
        $dom = $this->parse_html5($content, $charset);
        $this->add_document($dom);
        $base = $this->filter_relative_x_path('descendant-or-self::base')->extract(['href']);
        $base_href = current($base);
        if (\count($base) && $base_href) {
            if ($this->base_href) {
                $link_node = $dom->create_element('a');
                $link_node->set_attribute('href', $base_href);
                $link = new Link($link_node, $this->base_href);
                $this->base_href = $link->get_uri();
            } else {
                $this->base_href = $base_href;
            }
        }
    }
    /**
     * Adds an XML content to the list of nodes.
     *
     * The libxml errors are disabled when the content is parsed.
     *
     * If you want to get parsing errors, be sure to enable
     * internal errors via libxml_use_internal_errors(true)
     * and then, get the errors via libxml_get_errors(). Be
     * sure to clear errors with libxml_clear_errors() afterward.
     *
     * @param int $options Bitwise OR of the libxml option constants
     *                     LIBXML_PARSEHUGE is dangerous, see
     *                     http://symfony.com/blog/security-release-symfony-2-0-17-released
     */
    public function add_xml_content(string $content, string $charset = 'UTF-8', int $options = \LIBXML_NONET): void
    {
        // remove the default namespace if it's the only namespace to make XPath expressions simpler
        if (!str_contains($content, 'xmlns:')) {
            $content = str_replace('xmlns', 'ns', $content);
        }
        $internal_errors = libxml_use_internal_errors(true);
        $dom = new \Dom_Document('1.0', $charset);
        $dom->validate_on_parse = true;
        if ('' !== trim($content)) {
            @$dom->load_xml($content, $options);
        }
        libxml_use_internal_errors($internal_errors);
        $this->add_document($dom);
        $this->is_html = false;
    }
    /**
     * Adds a \DOMDocument to the list of nodes.
     */
    public function add_document(\Dom_Document $dom): void
    {
        if ($dom->document_element) {
            $this->add_node($dom->document_element);
        }
    }
    /**
     * Adds a \DOMNodeList to the list of nodes.
     *
     * @param \DOMNodeList<\DOMNode> $nodes
     */
    public function add_node_list(\Dom_Node_List $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof \Dom_Node) {
                $this->add_node($node);
            }
        }
    }
    /**
     * Adds an array of \DOMNode instances to the list of nodes.
     *
     * @param \DOMNode[] $nodes
     */
    public function add_nodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }
    }
    /**
     * Adds a \DOMNode instance to the list of nodes.
     */
    public function add_node(\Dom_Node $node): void
    {
        if ($node instanceof \Dom_Document) {
            $node = $node->document_element;
        }
        if (null !== $this->document && $this->document !== $node->owner_document) {
            throw new \InvalidArgumentException('Attaching DOM nodes from multiple documents in the same crawler is forbidden.');
        }
        $this->document ??= $node->owner_document;
        // Don't add duplicate nodes in the Crawler
        if (\in_array($node, $this->nodes, true)) {
            return;
        }
        $this->nodes[] = $node;
    }
    /**
     * Returns a node given its position in the node list.
     */
    public function eq(int $position): static
    {
        if (isset($this->nodes[$position])) {
            return $this->create_sub_crawler($this->nodes[$position]);
        }
        return $this->create_sub_crawler(null);
    }
    /**
     * Calls an anonymous function on each node of the list.
     *
     * The anonymous function receives the position and the node wrapped
     * in a Crawler instance as arguments.
     *
     * Example:
     *
     *     $crawler->filter('h1')->each(fn ($node, $i) => $node->text());
     *
     * @template R of mixed
     *
     * @param \Closure(static, int):R $closure
     *
     * @return list<R> An array of values returned by the anonymous function
     */
    public function each(\Closure $closure): array
    {
        $data = [];
        foreach ($this->nodes as $i => $node) {
            $data[] = $closure($this->create_sub_crawler($node), $i);
        }
        return $data;
    }
    /**
     * Slices the list of nodes by $offset and $length.
     */
    public function slice(int $offset = 0, ?int $length = null): static
    {
        return $this->create_sub_crawler(\array_slice($this->nodes, $offset, $length));
    }
    /**
     * Reduces the list of nodes by calling an anonymous function.
     *
     * To remove a node from the list, the anonymous function must return false.
     *
     * @param \Closure(static, int):bool $closure
     */
    public function reduce(\Closure $closure): static
    {
        $nodes = [];
        foreach ($this->nodes as $i => $node) {
            if (false !== $closure($this->create_sub_crawler($node), $i)) {
                $nodes[] = $node;
            }
        }
        return $this->create_sub_crawler($nodes);
    }
    /**
     * Returns the first node of the current selection.
     */
    public function first(): static
    {
        return $this->eq(0);
    }
    /**
     * Returns the last node of the current selection.
     */
    public function last(): static
    {
        return $this->eq(\count($this->nodes) - 1);
    }
    /**
     * Returns the siblings nodes of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function siblings(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        return $this->create_sub_crawler($this->sibling($this->get_node(0)->parent_node->first_child));
    }
    public function matches(string $selector): bool
    {
        if (!$this->nodes) {
            return false;
        }
        $converter = $this->create_css_selector_converter();
        $xpath = $converter->to_x_path($selector, 'self::');
        return 0 !== $this->filter_relative_x_path($xpath)->count();
    }
    /**
     * Return first parents (heading toward the document root) of the Element that matches the provided selector.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/closest#Polyfill
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function closest(string $selector): ?static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $dom_node = $this->get_node(0);
        while (null !== $dom_node && \XML_ELEMENT_NODE === $dom_node->node_type) {
            $node = $this->create_sub_crawler($dom_node);
            if ($node->matches($selector)) {
                return $node;
            }
            $dom_node = $node->get_node(0)->parent_node;
        }
        return null;
    }
    /**
     * Returns the next siblings nodes of the current selection.
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function next_all(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        return $this->create_sub_crawler($this->sibling($this->get_node(0)));
    }
    /**
     * Returns the previous sibling nodes of the current selection.
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function previous_all(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        return $this->create_sub_crawler($this->sibling($this->get_node(0), 'previousSibling'));
    }
    /**
     * Returns the ancestors of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function ancestors(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        $nodes = [];
        while ($node = $node->parent_node) {
            if (\XML_ELEMENT_NODE === $node->node_type) {
                $nodes[] = $node;
            }
        }
        return $this->create_sub_crawler($nodes);
    }
    /**
     * Returns the children nodes of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     * @throws \RuntimeException         If the CssSelector Component is not available and $selector is provided
     */
    public function children(?string $selector = null): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        if (null !== $selector) {
            $converter = $this->create_css_selector_converter();
            $xpath = $converter->to_x_path($selector, 'child::');
            return $this->filter_relative_x_path($xpath);
        }
        $node = $this->get_node(0)->first_child;
        return $this->create_sub_crawler($node ? $this->sibling($node) : []);
    }
    /**
     * Returns the attribute value of the first node of the list.
     *
     * @param string|null $default When not null: the value to return when the node or attribute is empty
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function attr(string $attribute, ?string $default = null): ?string
    {
        if (!$this->nodes) {
            if (null !== $default) {
                return $default;
            }
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        return $node->has_attribute($attribute) ? $node->get_attribute($attribute) : $default;
    }
    /**
     * Returns the node name of the first node of the list.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function node_name(): string
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        return $this->get_node(0)->node_name;
    }
    /**
     * Returns the text of the first node of the list.
     *
     * Pass true as the second argument to normalize whitespaces.
     *
     * @param string|null $default             When not null: the value to return when the current node is empty
     * @param bool        $normalizeWhitespace Whether whitespaces should be trimmed and normalized to single spaces
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function text(?string $default = null, bool $normalize_whitespace = true): string
    {
        if (!$this->nodes) {
            if (null !== $default) {
                return $default;
            }
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $text = $this->get_node(0)->node_value;
        if ($normalize_whitespace) {
            return $this->normalize_whitespace($text);
        }
        return $text;
    }
    /**
     * Returns only the inner text that is the direct descendent of the current node, excluding any child nodes.
     *
     * @param bool $normalizeWhitespace Whether whitespaces should be trimmed and normalized to single spaces
     */
    public function inner_text(bool $normalize_whitespace = true): string
    {
        foreach ($this->get_node(0)->child_nodes as $child_node) {
            if (\XML_TEXT_NODE !== $child_node->node_type && \XML_CDATA_SECTION_NODE !== $child_node->node_type) {
                continue;
            }
            if (!$normalize_whitespace) {
                return $child_node->node_value;
            }
            if ('' !== trim((string) $child_node->node_value)) {
                return $this->normalize_whitespace($child_node->node_value);
            }
        }
        return '';
    }
    /**
     * Returns the first node of the list as HTML.
     *
     * @param string|null $default When not null: the value to return when the current node is empty
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function html(?string $default = null): string
    {
        if (!$this->nodes) {
            if (null !== $default) {
                return $default;
            }
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        $owner = $node->owner_document;
        $html = '';
        foreach ($node->child_nodes as $child) {
            $html .= $owner->save_html($child);
        }
        return $html;
    }
    /**
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function outer_html(): string
    {
        if (!\count($this)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        $owner = $node->owner_document;
        return $owner->save_html($node);
    }
    /**
     * Evaluates an XPath expression.
     *
     * Since an XPath expression might evaluate to either a simple type or a \DOMNodeList,
     * this method will return either an array of simple types or a new Crawler instance.
     */
    public function evaluate(string $xpath): array|static
    {
        if (null === $this->document) {
            throw new \LogicException('Cannot evaluate the expression on an uninitialized crawler.');
        }
        $data = [];
        $domxpath = $this->create_domx_path($this->document, $this->find_namespace_prefixes($xpath));
        foreach ($this->nodes as $node) {
            $data[] = $domxpath->evaluate($xpath, $node);
        }
        if (isset($data[0]) && $data[0] instanceof \Dom_Node_List) {
            return $this->create_sub_crawler($data);
        }
        return $data;
    }
    /**
     * Extracts information from the list of nodes.
     *
     * You can extract attributes or/and the node value (_text).
     *
     * Example:
     *
     *     $crawler->filter('h1 a')->extract(['_text', 'href']);
     */
    public function extract(array $attributes): array
    {
        $count = \count($attributes);
        $data = [];
        foreach ($this->nodes as $node) {
            $elements = [];
            foreach ($attributes as $attribute) {
                if ('_text' === $attribute) {
                    $elements[] = $node->node_value;
                } elseif ('_name' === $attribute) {
                    $elements[] = $node->node_name;
                } else {
                    $elements[] = $node->get_attribute($attribute);
                }
            }
            $data[] = 1 === $count ? $elements[0] : $elements;
        }
        return $data;
    }
    /**
     * Filters the list of nodes with an XPath expression.
     *
     * The XPath expression is evaluated in the context of the crawler, which
     * is considered as a fake parent of the elements inside it.
     * This means that a child selector "div" or "./div" will match only
     * the div elements of the current crawler, not their children.
     */
    public function filter_x_path(string $xpath): static
    {
        $xpath = $this->relativize($xpath);
        // If we dropped all expressions in the XPath while preparing it, there would be no match
        if ('' === $xpath) {
            return $this->create_sub_crawler(null);
        }
        return $this->filter_relative_x_path($xpath);
    }
    /**
     * Filters the list of nodes with a CSS selector.
     *
     * This method only works if you have installed the CssSelector Symfony Component.
     *
     * @throws \LogicException if the CssSelector Component is not available
     */
    public function filter(string $selector): static
    {
        $converter = $this->create_css_selector_converter();
        // The CssSelector already prefixes the selector with descendant-or-self::
        return $this->filter_relative_x_path($converter->to_x_path($selector));
    }
    /**
     * Selects links by name or alt value for clickable images.
     */
    public function select_link(string $value): static
    {
        return $this->filter_relative_x_path(\sprintf('descendant-or-self::a[contains(concat(\' \', normalize-space(string(.)), \' \'), %1$s) or ./img[contains(concat(\' \', normalize-space(string(@alt)), \' \'), %1$s)]]', static::xpath_literal(' ' . $value . ' ')));
    }
    /**
     * Selects images by alt value.
     */
    public function select_image(string $value): static
    {
        $xpath = \sprintf('descendant-or-self::img[contains(normalize-space(string(@alt)), %s)]', static::xpath_literal($value));
        return $this->filter_relative_x_path($xpath);
    }
    /**
     * Selects a button by its text content, id, value, name or alt attribute.
     */
    public function select_button(string $value): static
    {
        return $this->filter_relative_x_path(\sprintf('descendant-or-self::input[((contains(%1$s, "submit") or contains(%1$s, "button")) and contains(concat(\' \', normalize-space(string(@value)), \' \'), %2$s)) or (contains(%1$s, "image") and contains(concat(\' \', normalize-space(string(@alt)), \' \'), %2$s)) or @id=%3$s or @name=%3$s] | descendant-or-self::button[contains(concat(\' \', normalize-space(string(.)), \' \'), %2$s) or contains(concat(\' \', normalize-space(string(@value)), \' \'), %2$s) or @id=%3$s or @name=%3$s]', 'translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")', static::xpath_literal(' ' . $value . ' '), static::xpath_literal($value)));
    }
    /**
     * Returns a Link object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the selected node is not instance of DOMElement
     */
    public function link(string $method = 'get'): Link
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        if (!$node instanceof \Dom_Element) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be instance of DOMElement, got "%s".', get_debug_type($node)));
        }
        return new Link($node, $this->base_href, $method);
    }
    /**
     * Returns an array of Link objects for the nodes in the list.
     *
     * @return Link[]
     *
     * @throws \InvalidArgumentException If the current node list contains non-DOMElement instances
     */
    public function links(): array
    {
        $links = [];
        foreach ($this->nodes as $node) {
            if (!$node instanceof \Dom_Element) {
                throw new \InvalidArgumentException(\sprintf('The current node list should contain only DOMElement instances, "%s" found.', get_debug_type($node)));
            }
            $links[] = new Link($node, $this->base_href, 'get');
        }
        return $links;
    }
    /**
     * Returns an Image object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty
     */
    public function image(): Image
    {
        if (!\count($this)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        if (!$node instanceof \Dom_Element) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be instance of DOMElement, got "%s".', get_debug_type($node)));
        }
        return new Image($node, $this->base_href);
    }
    /**
     * Returns an array of Image objects for the nodes in the list.
     *
     * @return Image[]
     */
    public function images(): array
    {
        $images = [];
        foreach ($this as $node) {
            if (!$node instanceof \Dom_Element) {
                throw new \InvalidArgumentException(\sprintf('The current node list should contain only DOMElement instances, "%s" found.', get_debug_type($node)));
            }
            $images[] = new Image($node, $this->base_href);
        }
        return $images;
    }
    /**
     * Returns a Form object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the selected node is not instance of DOMElement
     */
    public function form(?array $values = null, ?string $method = null): Form
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->get_node(0);
        if (!$node instanceof \Dom_Element) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be instance of DOMElement, got "%s".', get_debug_type($node)));
        }
        $form = new Form($node, $this->uri, $method, $this->base_href);
        if (null !== $values) {
            $form->set_values($values);
        }
        return $form;
    }
    /**
     * Overloads a default namespace prefix to be used with XPath and CSS expressions.
     */
    public function set_default_namespace_prefix(string $prefix): void
    {
        $this->default_namespace_prefix = $prefix;
    }
    public function register_namespace(string $prefix, string $namespace): void
    {
        $this->namespaces[$prefix] = $namespace;
    }
    /**
     * Converts string for XPath expressions.
     *
     * Escaped characters are: quotes (") and apostrophe (').
     *
     *  Examples:
     *
     *     echo Crawler::xpathLiteral('foo " bar');
     *     //prints 'foo " bar'
     *
     *     echo Crawler::xpathLiteral("foo ' bar");
     *     //prints "foo ' bar"
     *
     *     echo Crawler::xpathLiteral('a\'b"c');
     *     //prints concat('a', "'", 'b"c')
     */
    public static function xpath_literal(string $s): string
    {
        if (!str_contains($s, "'")) {
            return \sprintf("'%s'", $s);
        }
        if (!str_contains($s, '"')) {
            return \sprintf('"%s"', $s);
        }
        $string = $s;
        $parts = [];
        while (true) {
            if (false !== $pos = strpos($string, "'")) {
                $parts[] = \sprintf("'%s'", substr($string, 0, $pos));
                $parts[] = "\"'\"";
                $string = substr($string, $pos + 1);
            } else {
                $parts[] = "'{$string}'";
                break;
            }
        }
        return \sprintf('concat(%s)', implode(', ', $parts));
    }
    /**
     * Filters the list of nodes with an XPath expression.
     *
     * The XPath expression should already be processed to apply it in the context of each node.
     */
    private function filter_relative_x_path(string $xpath): static
    {
        $crawler = $this->create_sub_crawler(null);
        if (null === $this->document) {
            return $crawler;
        }
        $domxpath = $this->create_domx_path($this->document, $this->find_namespace_prefixes($xpath));
        foreach ($this->nodes as $node) {
            $crawler->add($domxpath->query($xpath, $node));
        }
        return $crawler;
    }
    /**
     * Make the XPath relative to the current context.
     *
     * The returned XPath will match elements matching the XPath inside the current crawler
     * when running in the context of a node of the crawler.
     */
    private function relativize(string $xpath): string
    {
        $expressions = [];
        // An expression which will never match to replace expressions which cannot match in the crawler
        // We cannot drop
        $non_matching_expression = 'a[name() = "b"]';
        $xpath_len = \strlen($xpath);
        $opened_brackets = 0;
        $start_position = strspn($xpath, " \t\n\r\x00\v");
        for ($i = $start_position; $i <= $xpath_len; ++$i) {
            $i += strcspn($xpath, '"\'[]|', $i);
            if ($i < $xpath_len) {
                switch ($xpath[$i]) {
                    case '"':
                    case "'":
                        if (false === $i = strpos($xpath, $xpath[$i], $i + 1)) {
                            return $xpath;
                            // The XPath expression is invalid
                        }
                        continue 2;
                    case '[':
                        ++$opened_brackets;
                        continue 2;
                    case ']':
                        --$opened_brackets;
                        continue 2;
                }
            }
            if ($opened_brackets) {
                continue;
            }
            if ($start_position < $xpath_len && '(' === $xpath[$start_position]) {
                // If the union is inside some braces, we need to preserve the opening braces and apply
                // the change only inside it.
                $j = 1 + strspn($xpath, "( \t\n\r\x00\v", $start_position + 1);
                $parenthesis = substr($xpath, $start_position, $j);
                $start_position += $j;
            } else {
                $parenthesis = '';
            }
            $expression = rtrim(substr($xpath, $start_position, $i - $start_position));
            if (str_starts_with($expression, 'self::*/')) {
                $expression = './' . substr($expression, 8);
            }
            // add prefix before absolute element selector
            if ('' === $expression) {
                $expression = $non_matching_expression;
            } elseif (str_starts_with($expression, '//')) {
                $expression = 'descendant-or-self::' . substr($expression, 2);
            } elseif (str_starts_with($expression, './/')) {
                $expression = 'descendant-or-self::' . substr($expression, 3);
            } elseif (str_starts_with($expression, './')) {
                $expression = 'self::' . substr($expression, 2);
            } elseif (str_starts_with($expression, 'child::')) {
                $expression = 'self::' . substr($expression, 7);
            } elseif ('/' === $expression[0] || '.' === $expression[0] || str_starts_with($expression, 'self::')) {
                $expression = $non_matching_expression;
            } elseif (str_starts_with($expression, 'descendant::')) {
                $expression = 'descendant-or-self::' . substr($expression, 12);
            } elseif (preg_match('/^(ancestor|ancestor-or-self|attribute|following|following-sibling|namespace|parent|preceding|preceding-sibling)::/', $expression)) {
                // the fake root has no parent, preceding or following nodes and also no attributes (even no namespace attributes)
                $expression = $non_matching_expression;
            } elseif (!str_starts_with($expression, 'descendant-or-self::')) {
                $expression = 'self::' . $expression;
            }
            $expressions[] = $parenthesis . $expression;
            if ($i === $xpath_len) {
                return implode(' | ', $expressions);
            }
            $i += strspn($xpath, " \t\n\r\x00\v", $i + 1);
            $start_position = $i + 1;
        }
        return $xpath;
        // The XPath expression is invalid
    }
    public function get_node(int $position): ?\Dom_Node
    {
        return $this->nodes[$position] ?? null;
    }
    public function count(): int
    {
        return \count($this->nodes);
    }
    /**
     * @return \ArrayIterator<int, \DOMNode>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->nodes);
    }
    protected function sibling(\Dom_Node $node, string $sibling_dir = 'nextSibling'): array
    {
        $nodes = [];
        $current_node = $this->get_node(0);
        do {
            if ($node !== $current_node && \XML_ELEMENT_NODE === $node->node_type) {
                $nodes[] = $node;
            }
        } while ($node = $node->{$sibling_dir});
        return $nodes;
    }
    private function parse_html5(string $html_content, string $charset = 'UTF-8'): \Dom_Document
    {
        $internal_errors = libxml_use_internal_errors(true);
        try {
            $document = \Dom\Html_Document::create_from_string($html_content, \Dom\HTML_NO_DEFAULT_NS, $charset);
        } catch (\Value_Error) {
            $document = \Dom\Html_Document::create_from_string($html_content, \Dom\HTML_NO_DEFAULT_NS);
        }
        libxml_use_internal_errors($internal_errors);
        $dom = new \Dom_Document('1.0', $document->input_encoding);
        $this->copy_from_html5to_dom($document->document_element, $dom);
        return $dom;
    }
    /**
     * Converts charset to HTML-entities to ensure valid parsing.
     */
    private function convert_to_html_entities(string $html_content, string $charset = 'UTF-8'): string
    {
        set_error_handler(static fn() => throw new \Exception());
        try {
            return mb_encode_numericentity($html_content, [0x80, 0x10ffff, 0, 0x1fffff], $charset);
        } catch (\Exception|\Value_Error) {
            try {
                $html_content = iconv($charset, 'UTF-8', $html_content);
                $html_content = mb_encode_numericentity($html_content, [0x80, 0x10ffff, 0, 0x1fffff], 'UTF-8');
            } catch (\Exception|\Value_Error) {
            }
            return $html_content;
        } finally {
            restore_error_handler();
        }
    }
    /**
     * @throws \InvalidArgumentException
     */
    private function create_domx_path(\Dom_Document $document, array $prefixes = []): \Domx_Path
    {
        $domxpath = new \Domx_Path($document);
        foreach ($prefixes as $prefix) {
            $namespace = $this->discover_namespace($domxpath, $prefix);
            if (null !== $namespace) {
                $domxpath->register_namespace($prefix, $namespace);
            }
        }
        return $domxpath;
    }
    /**
     * @throws \InvalidArgumentException
     */
    private function discover_namespace(\Domx_Path $domxpath, string $prefix): ?string
    {
        if (\array_key_exists($prefix, $this->namespaces)) {
            return $this->namespaces[$prefix];
        }
        if ($this->cached_namespaces->offsetExists($prefix)) {
            return $this->cached_namespaces[$prefix];
        }
        // ask for one namespace, otherwise we'd get a collection with an item for each node
        $namespaces = $domxpath->query(\sprintf('(//namespace::*[name()="%s"])[last()]', $this->default_namespace_prefix === $prefix ? '' : $prefix));
        return $this->cached_namespaces[$prefix] = $namespaces->item(0)?->node_value;
    }
    private function find_namespace_prefixes(string $xpath): array
    {
        if (preg_match_all('/(?P<prefix>[a-z_][a-z_0-9\-\.]*+):[^"\/:]/i', $xpath, $matches)) {
            return array_unique($matches['prefix']);
        }
        return [];
    }
    /**
     * Creates a crawler for some subnodes.
     *
     * @param \DOMNodeList<\DOMNode>|\DOMNode|\DOMNode[]|string|null $nodes
     */
    private function create_sub_crawler(\Dom_Node_List|\Dom_Node|array|string|null $nodes): static
    {
        $crawler = new static($nodes, $this->uri, $this->base_href);
        $crawler->is_html = $this->is_html;
        $crawler->document = $this->document;
        $crawler->namespaces = $this->namespaces;
        $crawler->cached_namespaces = $this->cached_namespaces;
        return $crawler;
    }
    /**
     * @throws \LogicException If the CssSelector Component is not available
     */
    private function create_css_selector_converter(): Css_Selector_Converter
    {
        if (!class_exists(Css_Selector_Converter::class)) {
            throw new \LogicException('To filter with a CSS selector, install the CssSelector component ("composer require symfony/css-selector"). Or use filterXpath instead.');
        }
        return new Css_Selector_Converter($this->is_html);
    }
    private function copy_from_html5to_dom(\Dom\Node $source, \Dom_Document $target): void
    {
        /** @var list<array{0: iterable<\Dom\Node>, 1: \DOMNode}> $stack */
        $stack = [[[$source], $target]];
        while ($stack) {
            [$children, $parent] = array_pop($stack);
            foreach ($children as $source) {
                if ($source instanceof \Dom\Character_Data) {
                    $parent->append_child(match (true) {
                        $source instanceof \Dom\Text => $target->create_text_node($source->data),
                        $source instanceof \Dom\Comment => $target->create_comment($source->data),
                        $source instanceof \Dom\Cdata_Section => $target->create_cdata_section($source->data),
                        $source instanceof \Dom\Processing_Instruction => $target->create_processing_instruction($source->target, $source->data),
                    });
                    continue;
                }
                if (!$source instanceof \Dom\Element) {
                    continue;
                }
                try {
                    $element = $target->create_element($source->tag_name);
                } catch (\Dom_Exception) {
                    continue;
                }
                foreach ($source->attributes as $attr) {
                    try {
                        $element->set_attribute($attr->name, $attr->value);
                    } catch (\Dom_Exception) {
                        // ignore invalid attribute name
                    }
                    if ('id' === $attr->name) {
                        $element->set_id_attribute('id', true);
                    }
                }
                $parent->append_child($element);
                $stack[] = [$source->child_nodes, $element];
            }
        }
    }
    private function normalize_whitespace(string $string): string
    {
        return trim((string) preg_replace("/(?:[ \n\r\t\f]{2,}+|[\n\r\t\f])/", ' ', $string), " \n\r\t\f");
    }
}