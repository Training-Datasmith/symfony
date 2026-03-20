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
namespace Symfony\Component\Dependency_Injection\Exception;

/**
 * This exception is thrown when a circular reference is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Service_Circular_Reference_Exception extends RuntimeException
{
    public function __construct(private readonly string $service_id, private readonly array $path, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Circular reference detected for service "%s", path: "%s".', $service_id, implode(' -> ', $path)), 0, $previous);
    }
    public function get_service_id(): string
    {
        return $this->service_id;
    }
    public function get_path(): array
    {
        return $this->path;
    }
}