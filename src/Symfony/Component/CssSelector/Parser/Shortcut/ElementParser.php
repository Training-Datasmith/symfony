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
 * CSS selector element parser shortcut.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Element_Parser implements Parser_Interface
{
    public function parse(string $source): array
    {
        // Matches an optional namespace, required element or `*`
        // $source = 'testns|testel';
        // $matches = array (size=3)
        //     0 => string 'testns|testel' (length=13)
        //     1 => string 'testns' (length=6)
        //     2 => string 'testel' (length=6)
        if (preg_match('/^(?:([a-z]++)\|)?([\w-]++|\*)$/i', trim($source), $matches)) {
            return [new Selector_Node(new Element_Node($matches[1] ?: null, $matches[2]))];
        }
        return [];
    }
}