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
class Skipping_Resource_Checker implements Resource_Checker_Interface
{
    private array $skipped_resource_types;
    /**
     * @param class-string<ResourceInterface>[] $skippedResourceTypes
     */
    public function __construct(array $skipped_resource_types = [])
    {
        $this->skipped_resource_types = array_flip($skipped_resource_types);
    }
    public function supports(Resource_Interface $metadata): bool
    {
        return !$this->skipped_resource_types || isset($this->skipped_resource_types[$metadata::class]);
    }
    public function is_fresh(Resource_Interface $resource, int $timestamp): bool
    {
        return true;
    }
    public function __serialize(): array
    {
        return ['skippedResourceTypes' => $this->skipped_resource_types];
    }
}