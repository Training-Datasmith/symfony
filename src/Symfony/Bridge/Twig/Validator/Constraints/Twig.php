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
namespace Symfony\Bridge\Twig\Validator\Constraints;

use Symfony\Component\Validator\Attribute\Has_Named_Arguments;
use Symfony\Component\Validator\Constraint;
/**
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Twig extends Constraint
{
    public const INVALID_TWIG_ERROR = 'e7fc55d5-e586-4cc1-924e-d27ee7fcd1b5';
    protected const ERROR_NAMES = [self::INVALID_TWIG_ERROR => 'INVALID_TWIG_ERROR'];
    #[Has_Named_Arguments]
    public function __construct(public string $message = 'This value is not a valid Twig template.', public bool $skip_deprecations = true, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }
}