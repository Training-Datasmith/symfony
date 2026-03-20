<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Tests\Fixtures\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\NodeInterface;

class CustomNodeDefinition extends NodeDefinition
{
    protected function createNode(): NodeInterface
    {
        return new CustomNode();
    }
}
