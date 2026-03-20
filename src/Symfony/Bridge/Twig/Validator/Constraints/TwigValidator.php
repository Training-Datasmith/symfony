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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraint_Validator;
use Symfony\Component\Validator\Exception\Unexpected_Type_Exception;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\Array_Loader;
use Twig\Source;
/**
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
class Twig_Validator extends Constraint_Validator
{
    public function __construct(private readonly Environment $twig)
    {
    }
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Twig) {
            throw new Unexpected_Type_Exception($constraint, Twig::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }
        $value = (string) $value;
        $real_loader = $this->twig->get_loader();
        try {
            $temporary_loader = new Array_Loader([$value]);
            $this->twig->set_loader($temporary_loader);
            if (!$constraint->skip_deprecations) {
                $prev_error_handler = set_error_handler(static function ($level, $message, $file, $line) use (&$prev_error_handler) {
                    if (\E_USER_DEPRECATED !== $level) {
                        return $prev_error_handler ? $prev_error_handler($level, $message, $file, $line) : false;
                    }
                    $template_line = 0;
                    if (preg_match('/ at line (\d+)[ .]/', $message, $matches)) {
                        $template_line = $matches[1];
                    }
                    throw new Error($message, $template_line);
                });
            }
            try {
                $this->twig->parse($this->twig->tokenize(new Source($value, '')));
            } finally {
                if (!$constraint->skip_deprecations) {
                    restore_error_handler();
                }
            }
        } catch (Error $e) {
            $this->context->build_violation($constraint->message)->set_parameter('{{ error }}', $e->get_message())->set_parameter('{{ line }}', $e->get_template_line())->set_code(Twig::INVALID_TWIG_ERROR)->add_violation();
        } finally {
            $this->twig->set_loader($real_loader);
        }
    }
}