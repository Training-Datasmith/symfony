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
namespace Symfony\Component\Dom_Crawler\Field;

/**
 * TextareaFormField represents a textarea form field (an HTML textarea tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Textarea_Form_Field extends Form_Field
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('textarea' !== $this->node->node_name) {
            throw new \LogicException(\sprintf('A TextareaFormField can only be created from a textarea tag (%s given).', $this->node->node_name));
        }
        $this->value = '';
        foreach ($this->node->child_nodes as $node) {
            $this->value .= $node->whole_text;
        }
    }
}