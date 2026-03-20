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
namespace Symfony\Component\Console\Event;

use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Event dispatched when constraint validation is needed for a question.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class Question_Answered_Event extends Event
{
    private array $violations = [];
    public function __construct(public readonly mixed $value, public readonly array $constraints)
    {
    }
    public function add_violation(string $message): void
    {
        $this->violations[] = $message;
    }
    public function get_violations(): array
    {
        return $this->violations;
    }
    public function has_violations(): bool
    {
        return (bool) $this->violations;
    }
}