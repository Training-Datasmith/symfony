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
namespace Symfony\Bridge\Php_Unit\Legacy;

/**
 * @internal
 */
trait Constraint_Logic_Trait
{
    private function do_evaluate($other, $description, $return_result): ?bool
    {
        $success = false;
        if ($this->matches($other)) {
            $success = true;
        }
        if ($return_result) {
            return $success;
        }
        if (!$success) {
            $this->fail($other, $description);
        }
        return null;
    }
    private function do_additional_failure_description($other): string
    {
        return '';
    }
    private function do_count(): int
    {
        return 1;
    }
    private function do_failure_description($other): string
    {
        return $this->exporter()->export($other) . ' ' . $this->to_string();
    }
    private function do_matches($other): bool
    {
        return false;
    }
    private function do_to_string(): string
    {
        return '';
    }
}