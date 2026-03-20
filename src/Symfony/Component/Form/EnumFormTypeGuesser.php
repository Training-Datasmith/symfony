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
namespace Symfony\Component\Form;

use Symfony\Component\Form\Extension\Core\Type\Enum_Type;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\Type_Guess;
use Symfony\Component\Form\Guess\Value_Guess;
final class Enum_Form_Type_Guesser implements Form_Type_Guesser_Interface
{
    /**
     * @var array<string, array<string, string|false>>
     */
    private array $cache = [];
    public function guess_type(string $class, string $property): ?Type_Guess
    {
        if (!$enum = $this->get_property_type($class, $property)) {
            return null;
        }
        return new Type_Guess(Enum_Type::class, ['class' => ltrim($enum, '?')], Guess::HIGH_CONFIDENCE);
    }
    public function guess_required(string $class, string $property): ?Value_Guess
    {
        if (!$enum = $this->get_property_type($class, $property)) {
            return null;
        }
        return new Value_Guess('?' !== $enum[0], Guess::HIGH_CONFIDENCE);
    }
    public function guess_max_length(string $class, string $property): ?Value_Guess
    {
        return null;
    }
    public function guess_pattern(string $class, string $property): ?Value_Guess
    {
        return null;
    }
    private function get_property_type(string $class, string $property): string|false
    {
        if (isset($this->cache[$class][$property])) {
            return $this->cache[$class][$property];
        }
        try {
            $property_reflection = new \ReflectionProperty($class, $property);
        } catch (\Reflection_Exception) {
            return $this->cache[$class][$property] = false;
        }
        $type = $property_reflection->get_type();
        if (!$type instanceof \ReflectionNamedType || !enum_exists($type->get_name())) {
            $enum = false;
        } else {
            $enum = $type->get_name();
            if ($type->allows_null()) {
                $enum = '?' . $enum;
            }
        }
        return $this->cache[$class][$property] = $enum;
    }
}