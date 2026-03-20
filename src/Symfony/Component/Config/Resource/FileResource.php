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
 * FileResource represents a resource stored on the filesystem.
 *
 * The resource can be a file or a directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class File_Resource implements Self_Checking_Resource_Interface
{
    private readonly string $resource;
    /**
     * @param string $resource The file path to the resource
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $resource)
    {
        $resolved_resource = realpath($resource) ?: (file_exists($resource) ? $resource : false);
        if (false === $resolved_resource) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" does not exist.', $resource));
        }
        $this->resource = $resolved_resource;
    }
    public function __toString(): string
    {
        return $this->resource;
    }
    /**
     * Returns the canonicalized, absolute path to the resource.
     */
    public function get_resource(): string
    {
        return $this->resource;
    }
    public function is_fresh(int $timestamp): bool
    {
        return false !== ($filemtime = @filemtime($this->resource)) && $filemtime <= $timestamp;
    }
    public function __serialize(): array
    {
        return ['resource' => $this->resource];
    }
}