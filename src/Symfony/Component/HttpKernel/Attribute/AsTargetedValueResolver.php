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
namespace Symfony\Component\Http_Kernel\Attribute;

/**
 * Service tag to autoconfigure targeted value resolvers.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class As_Targeted_Value_Resolver
{
    /**
     * @param string|null $name The name with which the resolver can be targeted
     */
    public function __construct(public readonly ?string $name = null)
    {
    }
}