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
namespace Symfony\Component\Http_Kernel\Controller_Metadata;

/**
 * Builds method argument data.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
interface Argument_Metadata_Factory_Interface
{
    /**
     * @return ArgumentMetadata[]
     */
    public function create_argument_metadata(string|object|array $controller, ?\Reflection_Function_Abstract $reflector = null): array;
}