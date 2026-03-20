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
namespace Symfony\Component\Config\Resource;

use Symfony\Component\Config\Resource_Checker_Interface;
/**
 * Resource checker for instances of SelfCheckingResourceInterface.
 *
 * As these resources perform the actual check themselves, we can provide
 * this class as a standard way of validating them.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Self_Checking_Resource_Checker implements Resource_Checker_Interface
{
    // Common shared cache, because this checker can be used in different
    // situations. For example, when using the full stack framework, the router
    // and the container have their own cache. But they may check the very same
    // resources
    private static array $cache = [];
    public function supports(Resource_Interface $metadata): bool
    {
        return $metadata instanceof Self_Checking_Resource_Interface;
    }
    /**
     * @param SelfCheckingResourceInterface $resource
     */
    public function is_fresh(Resource_Interface $resource, int $timestamp): bool
    {
        $key = "{$resource}:{$timestamp}";
        return self::$cache[$key] ??= $resource->is_fresh($timestamp);
    }
}