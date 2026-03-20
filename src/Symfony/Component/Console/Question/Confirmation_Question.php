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
namespace Symfony\Component\Console\Question;

/**
 * Represents a yes/no question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Confirmation_Question extends Question
{
    /**
     * @param string $question        The question to ask to the user
     * @param bool   $default         The default answer to return, true or false
     * @param string $trueAnswerRegex A regex to match the "yes" answer
     */
    public function __construct(string $question, bool $default = true, private readonly string $true_answer_regex = '/^y/i')
    {
        parent::__construct($question, $default);
        $this->set_normalizer($this->get_default_normalizer());
    }
    /**
     * Returns the default answer normalizer.
     */
    private function get_default_normalizer(): callable
    {
        $default = $this->get_default();
        $regex = $this->true_answer_regex;
        return static function ($answer) use ($default, $regex): bool {
            if (\is_bool($answer)) {
                return $answer;
            }
            $answer_is_true = (bool) preg_match($regex, $answer);
            if (false === $default) {
                return $answer && $answer_is_true;
            }
            return '' === $answer || $answer_is_true;
        };
    }
}