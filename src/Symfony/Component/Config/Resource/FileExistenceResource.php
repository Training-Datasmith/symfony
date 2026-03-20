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

/**
 * FileExistenceResource represents a resource stored on the filesystem.
 * Freshness is only evaluated against resource creation or deletion.
 *
 * The resource can be a file or a directory.
 *
 * @author Charles-Henri Bruyand <charleshenri.bruyand@gmail.com>
 *
 * @final
 */
class File_Existence_Resource implements Self_Checking_Resource_Interface
{
    private readonly bool $exists;
    /**
     * @param string $resource The file path to the resource
     */
    public function __construct(private readonly string $resource)
    {
        $this->exists = file_exists($resource);
    }
    public function __toString(): string
    {
        return 'existence.' . $this->resource;
    }
    public function get_resource(): string
    {
        return $this->resource;
    }
    public function is_fresh(int $timestamp): bool
    {
        return file_exists($this->resource) === $this->exists;
    }
    public function __serialize(): array
    {
        return ['resource' => $this->resource, 'exists' => $this->exists];
    }
}