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
namespace Symfony\Component\Dom_Crawler;

/**
 * Link represents an HTML link (an HTML a, area or link tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Link extends Abstract_Uri_Element
{
    protected function get_raw_uri(): string
    {
        return $this->node->get_attribute('href');
    }
    protected function set_node(\Dom_Element $node): void
    {
        if ('a' !== $node->node_name && 'area' !== $node->node_name && 'link' !== $node->node_name) {
            throw new \LogicException(\sprintf('Unable to navigate from a "%s" tag.', $node->node_name));
        }
        $this->node = $node;
    }
}