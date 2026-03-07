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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Conditionally apply validation constraints based on an expression using the ExpressionLanguage syntax.
 *
 * @see https://symfony.com/doc/current/components/expression_language.html
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class When extends Composite
{
    public array|Constraint $constraints = [];
    public array $values = [];

    /**
     * @param string|Expression|\Closure(object): bool $expression  The condition to evaluate, either as a closure or using the ExpressionLanguage syntax
     * @param Constraint[]|Constraint|null             $constraints One or multiple constraints that are applied if the expression returns true
     * @param array<string,mixed>|null                 $values      The values of the custom variables used in the expression (defaults to [])
     * @param string[]|null                            $groups
     * @param Constraint[]|Constraint                  $otherwise   One or multiple constraints that are applied if the expression returns false
     */
    public function __construct(public string|Expression|\Closure $expression, array|Constraint|null $constraints = null, ?array $values = null, ?array $groups = null, mixed $payload = null, ?array $options = null, public array|Constraint $otherwise = [])
    {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new LogicException(\sprintf('The "symfony/expression-language" component is required to use the "%s" constraint. Try running "composer require symfony/expression-language".', self::class));
        }

        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        if (null === $constraints) {
            throw new MissingOptionsException(\sprintf('The options "constraints" must be set for constraint "%s".', self::class), ['constraints']);
        }
        $this->constraints = $constraints;

        parent::__construct(null, $groups, $payload);

        $this->values = $values ?? $this->values;
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    protected function getCompositeOption(): array|string
    {
        return ['constraints', 'otherwise'];
    }
}
