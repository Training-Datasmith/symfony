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
namespace Symfony\Bridge\Php_Unit\Deprecation_Error_Handler;

/**
 * @internal
 */
final class Deprecation_Group
{
    private int $count = 0;
    /**
     * @var DeprecationNotice[] keys are messages
     */
    private array $deprecation_notices = [];
    public function add_notice_from_object(string $message, string $class, string $method): void
    {
        $this->deprecation_notice($message)->add_object_occurrence($class, $method);
        $this->add_notice();
    }
    public function add_notice_from_procedural_code(string $message): void
    {
        $this->deprecation_notice($message)->add_procedural_occurrence();
        $this->add_notice();
    }
    public function add_notice(): void
    {
        ++$this->count;
    }
    private function deprecation_notice(string $message): Deprecation_Notice
    {
        return $this->deprecation_notices[$message] ?? $this->deprecation_notices[$message] = new Deprecation_Notice();
    }
    public function count(): int
    {
        return $this->count;
    }
    public function notices(): array
    {
        return $this->deprecation_notices;
    }
}