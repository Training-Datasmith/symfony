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
trait Constraint_Trait_For_V9
{
    use Constraint_Logic_Trait;
    public function evaluate($other, string $description = '', bool $return_result = false): ?bool
    {
        return $this->do_evaluate($other, $description, $return_result);
    }
    public function count(): int
    {
        return $this->do_count();
    }
    public function to_string(): string
    {
        return $this->do_to_string();
    }
    protected function additional_failure_description($other): string
    {
        return $this->do_additional_failure_description($other);
    }
    protected function failure_description($other): string
    {
        return $this->do_failure_description($other);
    }
    protected function matches($other): bool
    {
        return $this->do_matches($other);
    }
}