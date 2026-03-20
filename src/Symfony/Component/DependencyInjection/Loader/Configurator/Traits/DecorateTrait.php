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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator\Traits;

use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
trait Decorate_Trait
{
    /**
     * Sets the service that this service is decorating.
     *
     * @param string|null $id The decorated service id, use null to remove decoration
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    final public function decorate(?string $id, ?string $renamed_id = null, int $priority = 0, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        $this->definition->set_decorated_service($id, $renamed_id, $priority, $invalid_behavior);
        return $this;
    }
    /**
     * Sets the tag that this definition is decorating.
     *
     * When decorating a tag, this definition acts as a template to create decorator services
     * for each service that has the specified tag.
     *
     * @return $this
     */
    final public function decorate_tag(string $tag, int $priority = 0, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        $tag_attributes = ['decorates_tag' => $tag, 'priority' => $priority];
        if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE !== $invalid_behavior) {
            $tag_attributes['on_invalid'] = $invalid_behavior;
        }
        $this->definition->add_resource_tag('container.tag_decorator', $tag_attributes);
        return $this;
    }
}