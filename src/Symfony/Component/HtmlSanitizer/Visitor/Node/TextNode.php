<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Visitor\Node;

use Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final readonly class TextNode implements NodeInterface
{
    public function __construct(
        private NodeInterface $parentNode,
        private string $text,
    ) {
    }

    public function addChild(NodeInterface $node): void
    {
        throw new \LogicException('Text nodes cannot have children.');
    }

    public function getParent(): \Symfony\Component\HtmlSanitizer\Visitor\Node\NodeInterface
    {
        return $this->parentNode;
    }

    public function render(): string
    {
        return StringSanitizer::encodeHtmlEntities($this->text);
    }
}
