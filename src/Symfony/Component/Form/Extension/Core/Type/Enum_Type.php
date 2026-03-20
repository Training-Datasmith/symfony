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
namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Contracts\Translation\Translatable_Interface;
/**
 * A choice type for native PHP enums.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class Enum_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_required(['class'])->set_allowed_types('class', 'string')->set_allowed_values('class', enum_exists(...))->set_default('choices', static fn(Options $options): array => $options['class']::cases())->set_default('choice_label', static fn(Options $options) => static function (\Unit_Enum $choice, int|string $key): string|Translatable_Interface {
            if (\is_int($key)) {
                // Key is an integer, use the enum's name (or translatable)
                return $choice instanceof Translatable_Interface ? $choice : $choice->name;
            }
            // Key is a string, use it as the label
            return $key;
        })->set_default('choice_value', static function (Options $options): ?\Closure {
            if (!is_a($options['class'], \Backed_Enum::class, true)) {
                return null;
            }
            return static function (?\Backed_Enum $choice): ?string {
                if (null === $choice) {
                    return null;
                }
                return (string) $choice->value;
            };
        });
    }
    public function get_parent(): string
    {
        return Choice_Type::class;
    }
}