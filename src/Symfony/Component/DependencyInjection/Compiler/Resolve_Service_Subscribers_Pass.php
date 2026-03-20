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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Psr\Container\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Contracts\Service\Service_Provider_Interface;
/**
 * Compiler pass to inject their service locator to service subscribers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_Service_Subscribers_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private ?string $service_locator = null;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Reference && $this->service_locator && \in_array((string) $value, [Container_Interface::class, Service_Provider_Interface::class], true)) {
            return new Reference($this->service_locator);
        }
        if (!$value instanceof Definition) {
            return parent::process_value($value, $is_root);
        }
        $service_locator = $this->service_locator;
        $this->service_locator = null;
        if ($value->has_tag('container.service_subscriber.locator')) {
            $this->service_locator = $value->get_tag('container.service_subscriber.locator')[0]['id'];
            $value->clear_tag('container.service_subscriber.locator');
        }
        try {
            return parent::process_value($value);
        } finally {
            $this->service_locator = $service_locator;
        }
    }
}