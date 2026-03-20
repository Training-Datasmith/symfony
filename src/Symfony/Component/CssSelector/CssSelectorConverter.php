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
namespace Symfony\Component\Css_Selector;

use Symfony\Component\Css_Selector\Parser\Shortcut\Class_Parser;
use Symfony\Component\Css_Selector\Parser\Shortcut\Element_Parser;
use Symfony\Component\Css_Selector\Parser\Shortcut\Empty_String_Parser;
use Symfony\Component\Css_Selector\Parser\Shortcut\Hash_Parser;
use Symfony\Component\Css_Selector\X_Path\Extension\Html_Extension;
use Symfony\Component\Css_Selector\X_Path\Translator;
/**
 * CssSelectorConverter is the main entry point of the component and can convert CSS
 * selectors to XPath expressions.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Css_Selector_Converter
{
    public static int $max_cached_items = 1024;
    private readonly Translator $translator;
    private array $cache;
    private static array $xml_cache = [];
    private static array $html_cache = [];
    /**
     * @param bool $html Whether HTML support should be enabled. Disable it for XML documents
     */
    public function __construct(bool $html = true)
    {
        $this->translator = new Translator();
        if ($html) {
            $this->translator->register_extension(new Html_Extension($this->translator));
            $this->cache =& self::$html_cache;
        } else {
            $this->cache =& self::$xml_cache;
        }
        $this->translator->register_parser_shortcut(new Empty_String_Parser())->register_parser_shortcut(new Element_Parser())->register_parser_shortcut(new Class_Parser())->register_parser_shortcut(new Hash_Parser());
    }
    /**
     * Translates a CSS expression to its XPath equivalent.
     *
     * Optionally, a prefix can be added to the resulting XPath
     * expression with the $prefix parameter.
     */
    public function to_x_path(string $css_expr, string $prefix = 'descendant-or-self::'): string
    {
        $cache_key = $prefix . "\x00" . $css_expr;
        if (isset($this->cache[$cache_key])) {
            // Move the item last in cache (LRU)
            $value = $this->cache[$cache_key];
            unset($this->cache[$cache_key]);
            return $this->cache[$cache_key] = $value;
        }
        if (\count($this->cache) >= self::$max_cached_items) {
            // Evict the oldest entry
            unset($this->cache[array_key_first($this->cache)]);
        }
        return $this->cache[$cache_key] = $this->translator->css_to_x_path($css_expr, $prefix);
    }
}