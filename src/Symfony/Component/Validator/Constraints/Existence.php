<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Existence extends Composite
{
    public function __construct(public array|Constraint $constraints = [], ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
