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
namespace Symfony\Component\Css_Selector\Parser\Shortcut;

use Symfony\Component\Css_Selector\Node\Element_Node;
use Symfony\Component\Css_Selector\Node\Selector_Node;
use Symfony\Component\Css_Selector\Parser\Parser_Interface;
/**
 * CSS selector class parser shortcut.
 *
 * This shortcut ensure compatibility with previous version.
 * - The parser fails to parse an empty string.
 * - In the previous version, an empty string matches each tags.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Empty_String_Parser implements Parser_Interface
{
    public function parse(string $source): array
    {
        // Matches an empty string
        if ('' == $source) {
            return [new Selector_Node(new Element_Node(null, '*'))];
        }
        return [];
    }
}