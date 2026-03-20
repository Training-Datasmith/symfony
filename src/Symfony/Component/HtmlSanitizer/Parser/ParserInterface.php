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
namespace Symfony\Component\Html_Sanitizer\Parser;

/**
 * Transforms an untrusted HTML input string into a DOM tree.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface Parser_Interface
{
    /**
     * Parse a given string and returns a DOMNode tree.
     *
     * This method must return null if the string cannot be parsed as HTML.
     *
     * @param string $context The name of the context element in which the HTML is parsed
     */
    public function parse(string $html, string $context = 'body'): \Dom\Node|\Dom_Node|null;
}