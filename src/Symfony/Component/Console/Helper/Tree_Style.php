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
namespace Symfony\Component\Console\Helper;

/**
 * Configures the output of the Tree helper.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class Tree_Style
{
    public function __construct(private string $prefix_end_has_next, private string $prefix_end_last, private string $prefix_left, private string $prefix_mid_has_next, private string $prefix_mid_last, private string $prefix_right)
    {
    }
    public static function box(): self
    {
        return new self('┃╸ ', '┗╸ ', '', '┃  ', '   ', '');
    }
    public static function box_double(): self
    {
        return new self('╠═ ', '╚═ ', '', '║  ', '  ', '');
    }
    public static function compact(): self
    {
        return new self('├ ', '└ ', '', '│ ', '  ', '');
    }
    public static function default(): self
    {
        return new self('├── ', '└── ', '', '│   ', '   ', '');
    }
    public static function light(): self
    {
        return new self('|-- ', '`-- ', '', '|   ', '    ', '');
    }
    public static function minimal(): self
    {
        return new self('. ', '. ', '', '. ', '  ', '');
    }
    public static function rounded(): self
    {
        return new self('├─ ', '╰─ ', '', '│  ', '   ', '');
    }
    /**
     * @internal
     */
    public function apply_prefixes(\Recursive_Tree_Iterator $iterator): void
    {
        $iterator->set_prefix_part(\Recursive_Tree_Iterator::PREFIX_LEFT, $this->prefix_left);
        $iterator->set_prefix_part(\Recursive_Tree_Iterator::PREFIX_MID_HAS_NEXT, $this->prefix_mid_has_next);
        $iterator->set_prefix_part(\Recursive_Tree_Iterator::PREFIX_MID_LAST, $this->prefix_mid_last);
        $iterator->set_prefix_part(\Recursive_Tree_Iterator::PREFIX_END_HAS_NEXT, $this->prefix_end_has_next);
        $iterator->set_prefix_part(\Recursive_Tree_Iterator::PREFIX_END_LAST, $this->prefix_end_last);
        $iterator->set_prefix_part(\Recursive_Tree_Iterator::PREFIX_RIGHT, $this->prefix_right);
    }
}