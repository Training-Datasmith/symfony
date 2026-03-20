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
namespace Symfony\Component\Css_Selector\X_Path;

use Symfony\Component\Css_Selector\Node\Selector_Node;
/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
interface Translator_Interface
{
    /**
     * Translates a CSS selector to an XPath expression.
     */
    public function css_to_x_path(string $css_expr, string $prefix = 'descendant-or-self::'): string;
    /**
     * Translates a parsed selector node to an XPath expression.
     */
    public function selector_to_x_path(Selector_Node $selector, string $prefix = 'descendant-or-self::'): string;
}