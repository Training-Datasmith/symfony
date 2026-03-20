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
class Controller_Arguments_Metadata extends Controller_Metadata
{
    public function __construct(Controller_Event $controller_event, private readonly Controller_Arguments_Event $controller_arguments_event)
    {
        parent::__construct($controller_event);
    }
    /**
     * @return list<mixed>
     */
    public function get_arguments(): array
    {
        return $this->controller_arguments_event->get_arguments();
    }
    /**
     * @return array<string, mixed>
     */
    public function get_named_arguments(): array
    {
        return $this->controller_arguments_event->get_named_arguments();
    }
    public function evaluate(mixed $value, ?Expression_Language $expression_language): mixed
    {
        return $this->controller_arguments_event->evaluate($value, $expression_language);
    }
}