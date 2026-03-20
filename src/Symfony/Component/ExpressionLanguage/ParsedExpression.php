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
namespace Symfony\Component\Expression_Language;

use Symfony\Component\Expression_Language\Node\Node;
/**
 * Represents an already parsed expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parsed_Expression extends Expression
{
    public function __construct(string $expression, private readonly Node $nodes)
    {
        parent::__construct($expression);
    }
    public function get_nodes(): Node
    {
        return $this->nodes;
    }
}