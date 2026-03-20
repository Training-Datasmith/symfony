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
namespace Symfony\Component\Css_Selector\Exception;

use Symfony\Component\Css_Selector\Parser\Token;
/**
 * ParseException is thrown when a CSS selector syntax is not valid.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Syntax_Error_Exception extends Parse_Exception
{
    public static function unexpected_token(string $expected_value, Token $found_token): self
    {
        return new self(\sprintf('Expected %s, but %s found.', $expected_value, $found_token));
    }
    public static function pseudo_element_found(string $pseudo_element, string $unexpected_location): self
    {
        return new self(\sprintf('Unexpected pseudo-element "::%s" found %s.', $pseudo_element, $unexpected_location));
    }
    public static function unclosed_string(int $position): self
    {
        return new self(\sprintf('Unclosed/invalid string at %s.', $position));
    }
    public static function nested_not(): self
    {
        return new self('Got nested ::not().');
    }
    public static function not_at_the_start_of_a_selector(string $pseudo_element): self
    {
        return new self(\sprintf('Got immediate child pseudo-element ":%s" not at the start of a selector', $pseudo_element));
    }
    public static function string_as_function_argument(): self
    {
        return new self('String not allowed as function argument.');
    }
}