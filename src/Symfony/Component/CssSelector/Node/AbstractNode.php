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
namespace Symfony\Component\Css_Selector\Node;

/**
 * Abstract base node class.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Abstract_Node implements Node_Interface
{
    private string $node_name;
    public function get_node_name(): string
    {
        return $this->node_name ??= preg_replace('~.*\\\\([^\\\\]+)Node$~', '$1', static::class);
    }
}