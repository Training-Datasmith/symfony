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
namespace Symfony\Component\Css_Selector\Parser;

use Symfony\Component\Css_Selector\Node\Selector_Node;
/**
 * CSS selector parser interface.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
interface Parser_Interface
{
    /**
     * Parses given selector source into an array of tokens.
     *
     * @return SelectorNode[]
     */
    public function parse(string $source): array;
}