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

use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\Type_Guess;
use Symfony\Component\Form\Guess\Value_Guess;
class Form_Type_Guesser_Chain implements Form_Type_Guesser_Interface
{
    private array $guessers = [];
    /**
     * @param FormTypeGuesserInterface[] $guessers
     *
     * @throws UnexpectedTypeException if any guesser does not implement FormTypeGuesserInterface
     */
    public function __construct(iterable $guessers)
    {
        $tmp_guessers = [];
        foreach ($guessers as $guesser) {
            if (!$guesser instanceof Form_Type_Guesser_Interface) {
                throw new Unexpected_Type_Exception($guesser, Form_Type_Guesser_Interface::class);
            }
            if ($guesser instanceof self) {
                $tmp_guessers[] = $guesser->guessers;
            } else {
                $tmp_guessers[] = [$guesser];
            }
        }
        $this->guessers = array_merge([], ...$tmp_guessers);
    }
    public function guess_type(string $class, string $property): ?Type_Guess
    {
        return $this->guess(static fn($guesser) => $guesser->guess_type($class, $property));
    }
    public function guess_required(string $class, string $property): ?Value_Guess
    {
        return $this->guess(static fn($guesser) => $guesser->guess_required($class, $property));
    }
    public function guess_max_length(string $class, string $property): ?Value_Guess
    {
        return $this->guess(static fn($guesser) => $guesser->guess_max_length($class, $property));
    }
    public function guess_pattern(string $class, string $property): ?Value_Guess
    {
        return $this->guess(static fn($guesser) => $guesser->guess_pattern($class, $property));
    }
    /**
     * Executes a closure for each guesser and returns the best guess from the
     * return values.
     *
     * @param \Closure $closure The closure to execute. Accepts a guesser
     *                          as argument and should return a Guess instance
     */
    private function guess(\Closure $closure): ?Guess
    {
        $guesses = [];
        foreach ($this->guessers as $guesser) {
            if ($guess = $closure($guesser)) {
                $guesses[] = $guess;
            }
        }
        return Guess::get_best_guess($guesses);
    }
}