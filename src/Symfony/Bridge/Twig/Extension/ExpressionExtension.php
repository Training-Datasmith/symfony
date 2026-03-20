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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Expression_Language\Expression;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * ExpressionExtension gives a way to create Expressions from a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Expression_Extension extends Abstract_Extension
{
    public function get_functions(): array
    {
        return [new Twig_Function('expression', $this->create_expression(...))];
    }
    public function create_expression(string $expression): Expression
    {
        return new Expression($expression);
    }
}