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
 * Image represents an HTML image (an HTML img tag).
 */
class Image extends Abstract_Uri_Element
{
    protected function get_raw_uri(): string
    {
        return $this->node->get_attribute('src');
    }
    protected function set_node(\Dom_Element $node): void
    {
        if ('img' !== $node->node_name) {
            throw new \LogicException(\sprintf('Unable to visualize a "%s" tag.', $node->node_name));
        }
        $this->node = $node;
    }
}