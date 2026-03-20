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
namespace Symfony\Component\Http_Kernel\Event;

use Symfony\Component\Expression_Language\Expression_Language;
/**
 * Provides read-only access to controller metadata.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Controller_Metadata
{
    public function __construct(private readonly Controller_Event $controller_event)
    {
    }
    public function get_controller(): callable
    {
        return $this->controller_event->get_controller();
    }
    public function get_reflector(): \Reflection_Function_Abstract
    {
        return $this->controller_event->get_controller_reflector();
    }
    /**
     * @template T of object
     *
     * @param class-string<T>|'*'|null $className
     *
     * @return ($className is null ? array<class-string, list<object>> : ($className is '*' ? list<object> : list<T>))
     */
    public function get_attributes(?string $class_name = null): array
    {
        return $this->controller_event->get_attributes($class_name);
    }
    public function evaluate(mixed $value, ?Expression_Language $expression_language): mixed
    {
        return $this->controller_event->evaluate($value, $expression_language);
    }
}