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
namespace Symfony\Component\Dependency_Injection\Argument;

use Symfony\Component\Dependency_Injection\Service_Locator as BaseServiceLocator;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Service_Locator extends Base_Service_Locator
{
    public function __construct(private readonly \Closure $factory, private array $service_map, private ?array $service_types = null)
    {
        parent::__construct($service_map);
    }
    public function get(string $id): mixed
    {
        return match (\count($this->service_map[$id] ?? [])) {
            0 => parent::get($id),
            1 => $this->service_map[$id][0],
            default => ($this->factory)(...$this->service_map[$id]),
        };
    }
    public function get_provided_services(): array
    {
        return $this->service_types ??= array_map(static fn(): string => '?', $this->service_map);
    }
}