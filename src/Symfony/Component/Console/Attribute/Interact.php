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
namespace Symfony\Component\Console\Attribute;

use Symfony\Component\Console\Exception\LogicException;
#[\Attribute(\Attribute::TARGET_METHOD)]
class Interact implements Interactive_Attribute_Interface
{
    private \ReflectionMethod $method;
    /**
     * @internal
     */
    public static function try_from(\ReflectionMethod $method): ?self
    {
        /** @var self|null $self */
        if (!$self = ($method->get_attributes(self::class)[0] ?? null)?->new_instance()) {
            return null;
        }
        if (!$method->is_public() || $method->is_static()) {
            throw new LogicException(\sprintf('The interactive method "%s::%s()" must be public and non-static.', $method->class, $method->get_name()));
        }
        if ('__invoke' === $method->get_name()) {
            throw new LogicException(\sprintf('The "%s::__invoke()" method cannot be used as an interactive method.', $method->class));
        }
        $self->method = $method;
        return $self;
    }
    /**
     * @internal
     */
    public function get_function(object $instance): \ReflectionFunction
    {
        return new \ReflectionFunction($this->method->get_closure($instance));
    }
}