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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Type_Guesser_Interface
{
    /**
     * Returns a field guess for a property name of a class.
     */
    public function guess_type(string $class, string $property): ?Guess\Type_Guess;
    /**
     * Returns a guess whether a property of a class is required.
     */
    public function guess_required(string $class, string $property): ?Guess\Value_Guess;
    /**
     * Returns a guess about the field's maximum length.
     */
    public function guess_max_length(string $class, string $property): ?Guess\Value_Guess;
    /**
     * Returns a guess about the field's pattern.
     */
    public function guess_pattern(string $class, string $property): ?Guess\Value_Guess;
}