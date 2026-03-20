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
namespace Symfony\Component\Config\Util;

use Symfony\Component\Config\Util\Exception\Invalid_Xml_Exception;
use Symfony\Component\Config\Util\Exception\Xml_Parsing_Exception;
use Symfony\Component\Filesystem\Filesystem;
/**
 * XMLUtils is a bunch of utility methods to XML operations.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 * @author Ole Rößner <ole@roessner.it>
 */
class Xml_Utils
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }
    /**
     * Parses an XML string.
     *
     * @param string               $content          An XML string
     * @param string|callable|null $schemaOrCallable An XSD schema file path, a callable, or null to disable validation
     *
     * @throws XmlParsingException When parsing of XML file returns error
     * @throws InvalidXmlException When parsing of XML with schema or callable produces any errors unrelated to the XML parsing itself
     * @throws \RuntimeException   When DOM extension is missing
     */
    public static function parse(string $content, string|callable|null $schema_or_callable = null): \Dom_Document
    {
        if (!\extension_loaded('dom')) {
            throw new \LogicException('Extension DOM is required.');
        }
        $internal_errors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \Dom_Document();
        $dom->validate_on_parse = true;
        if (!$dom->load_xml($content, \LIBXML_NONET | \LIBXML_COMPACT)) {
            throw new Xml_Parsing_Exception(implode("\n", static::get_xml_errors($internal_errors)));
        }
        $dom->normalize_document();
        libxml_use_internal_errors($internal_errors);
        foreach ($dom->child_nodes as $child) {
            if (\XML_DOCUMENT_TYPE_NODE === $child->node_type) {
                throw new Xml_Parsing_Exception('Document types are not allowed.');
            }
        }
        if (null !== $schema_or_callable) {
            $internal_errors = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $e = null;
            if (\is_callable($schema_or_callable)) {
                try {
                    $valid = $schema_or_callable($dom, $internal_errors);
                } catch (\Exception) {
                    $valid = false;
                }
            } elseif (is_file($schema_or_callable)) {
                $schema_source = (new Filesystem())->read_file($schema_or_callable);
                $valid = @$dom->schema_validate_source($schema_source);
            } else {
                libxml_use_internal_errors($internal_errors);
                throw new Xml_Parsing_Exception(\sprintf('Invalid XSD file: "%s".', $schema_or_callable));
            }
            if (!$valid) {
                $messages = static::get_xml_errors($internal_errors);
                if (!$messages) {
                    throw new Invalid_Xml_Exception('The XML is not valid.', 0, $e);
                }
                throw new Xml_Parsing_Exception(implode("\n", $messages), 0, $e);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($internal_errors);
        return $dom;
    }
    /**
     * Loads an XML file.
     *
     * @param string               $file             An XML file path
     * @param string|callable|null $schemaOrCallable An XSD schema file path, a callable, or null to disable validation
     *
     * @throws \InvalidArgumentException When loading of XML file returns error
     * @throws XmlParsingException       When XML parsing returns any errors
     * @throws \RuntimeException         When DOM extension is missing
     */
    public static function load_file(string $file, string|callable|null $schema_or_callable = null): \Dom_Document
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('Resource "%s" is not a file.', $file));
        }
        if (!is_readable($file)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" is not readable.', $file));
        }
        $content = (new Filesystem())->read_file($file);
        if ('' === trim($content)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" does not contain valid XML, it is empty.', $file));
        }
        try {
            return static::parse($content, $schema_or_callable);
        } catch (Invalid_Xml_Exception $e) {
            throw new Xml_Parsing_Exception(\sprintf('The XML file "%s" is not valid.', $file), 0, $e->get_previous());
        }
    }
    /**
     * Converts a \DOMElement object to a PHP array.
     *
     * The following rules applies during the conversion:
     *
     *  * Each tag is converted to a key value or an array
     *    if there is more than one "value"
     *
     *  * The content of a tag is set under a "value" key (<foo>bar</foo>)
     *    if the tag also has some nested tags
     *
     *  * The attributes are converted to keys (<foo foo="bar"/>)
     *
     *  * The nested-tags are converted to keys (<foo><foo>bar</foo></foo>)
     *
     * @param \DOMElement $element     A \DOMElement instance
     * @param bool        $checkPrefix Check prefix in an element or an attribute name
     */
    public static function convert_dom_element_to_array(\Dom_Element $element, bool $check_prefix = true): mixed
    {
        $prefix = $element->prefix;
        $empty = true;
        $config = [];
        foreach ($element->attributes as $name => $node) {
            if ($check_prefix && !\in_array($node->prefix, ['', $prefix], true)) {
                continue;
            }
            $config[$name] = static::phpize($node->value);
            $empty = false;
        }
        $node_value = false;
        foreach ($element->child_nodes as $node) {
            if ($node instanceof \Dom_Text) {
                if ('' !== trim((string) $node->node_value)) {
                    $node_value = trim((string) $node->node_value);
                    $empty = false;
                }
            } elseif ($check_prefix && $prefix != $node->prefix) {
                continue;
            } elseif (!$node instanceof \Dom_Comment) {
                $value = static::convert_dom_element_to_array($node, $check_prefix);
                $key = $node->local_name;
                if (isset($config[$key])) {
                    if (!\is_array($config[$key]) || !\is_int(key($config[$key]))) {
                        $config[$key] = [$config[$key]];
                    }
                    $config[$key][] = $value;
                } else {
                    $config[$key] = $value;
                }
                $empty = false;
            }
        }
        if (false !== $node_value) {
            $value = static::phpize($node_value);
            if (\count($config)) {
                $config['value'] = $value;
            } else {
                $config = $value;
            }
        }
        return !$empty ? $config : null;
    }
    /**
     * Converts an xml value to a PHP type.
     */
    public static function phpize(string|\Stringable $value): mixed
    {
        $value = (string) $value;
        $lowercase_value = strtolower($value);
        switch (true) {
            case 'null' === $lowercase_value:
                return null;
            case ctype_digit($value):
            case isset($value[1]) && '-' === $value[0] && ctype_digit(substr($value, 1)):
                $raw = $value;
                $cast = (int) $value;
                return self::is_octal($value) ? \intval($value, 8) : ($raw === (string) $cast ? $cast : $raw);
            case 'true' === $lowercase_value:
                return true;
            case 'false' === $lowercase_value:
                return false;
            case isset($value[1]) && '0b' == $value[0] . $value[1] && preg_match('/^0b[01]*$/', $value):
                return bindec($value);
            case is_numeric($value):
                return '0x' === $value[0] . $value[1] ? hexdec($value) : (float) $value;
            case preg_match('/^0x[0-9a-f]++$/i', $value):
                return hexdec($value);
            case preg_match('/^[+-]?[0-9]+(\.[0-9]+)?$/', $value):
                return (float) $value;
            default:
                return $value;
        }
    }
    protected static function get_xml_errors(bool $internal_errors): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = \sprintf('[%s %s] %s (in %s - line %d, column %d)', \LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR', $error->code, trim($error->message), $error->file ?: 'n/a', $error->line, $error->column);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($internal_errors);
        return $errors;
    }
    private static function is_octal(string $str): bool
    {
        if ('-' === $str[0]) {
            $str = substr($str, 1);
        }
        return $str === '0' . decoct(\intval($str, 8));
    }
}