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

/**
 * XPath expression translator abstract extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Abstract_Extension implements Extension_Interface
{
    public function get_node_translators(): array
    {
        return [];
    }
    public function get_combination_translators(): array
    {
        return [];
    }
    public function get_function_translators(): array
    {
        return [];
    }
    public function get_pseudo_class_translators(): array
    {
        return [];
    }
    public function get_attribute_matching_translators(): array
    {
        return [];
    }
    public function get_relative_combination_translators(): array
    {
        return [];
    }
}