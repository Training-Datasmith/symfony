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
namespace Symfony\Component\Css_Selector\X_Path\Extension;

use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator extension interface.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
interface Extension_Interface
{
    /**
     * Returns node translators.
     *
     * These callables will receive the node as first argument and the translator as second argument.
     *
     * @return callable[]
     */
    public function get_node_translators(): array;
    /**
     * Returns combination translators.
     *
     * @return callable[]
     */
    public function get_combination_translators(): array;
    /**
     * Returns function translators.
     *
     * @return callable[]
     */
    public function get_function_translators(): array;
    /**
     * Returns pseudo-class translators.
     *
     * @return callable[]
     */
    public function get_pseudo_class_translators(): array;
    /**
     * Returns attribute operation translators.
     *
     * @return callable[]
     */
    public function get_attribute_matching_translators(): array;
    /**
     * Returns combination translators found inside ":has()" relation.
     *
     * @return array<string, callable(XPathExpr, XPathExpr): XPathExpr>
     */
    public function get_relative_combination_translators(): array;
    /**
     * Returns extension name.
     */
    public function get_name(): string;
}