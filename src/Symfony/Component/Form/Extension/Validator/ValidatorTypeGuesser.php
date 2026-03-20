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
namespace Symfony\Component\Form\Extension\Validator;

use Symfony\Component\Form\Extension\Core\Type\Checkbox_Type;
use Symfony\Component\Form\Extension\Core\Type\Collection_Type;
use Symfony\Component\Form\Extension\Core\Type\Country_Type;
use Symfony\Component\Form\Extension\Core\Type\Currency_Type;
use Symfony\Component\Form\Extension\Core\Type\Date_Time_Type;
use Symfony\Component\Form\Extension\Core\Type\Date_Type;
use Symfony\Component\Form\Extension\Core\Type\Email_Type;
use Symfony\Component\Form\Extension\Core\Type\File_Type;
use Symfony\Component\Form\Extension\Core\Type\Integer_Type;
use Symfony\Component\Form\Extension\Core\Type\Language_Type;
use Symfony\Component\Form\Extension\Core\Type\Locale_Type;
use Symfony\Component\Form\Extension\Core\Type\Number_Type;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
use Symfony\Component\Form\Extension\Core\Type\Time_Type;
use Symfony\Component\Form\Extension\Core\Type\Url_Type;
use Symfony\Component\Form\Form_Type_Guesser_Interface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\Type_Guess;
use Symfony\Component\Form\Guess\Value_Guess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Country;
use Symfony\Component\Validator\Constraints\Currency;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\Is_False;
use Symfony\Component\Validator\Constraints\Is_True;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\Not_Blank;
use Symfony\Component\Validator\Constraints\Not_Null;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Mapping\Class_Metadata_Interface;
use Symfony\Component\Validator\Mapping\Factory\Metadata_Factory_Interface;
class Validator_Type_Guesser implements Form_Type_Guesser_Interface
{
    public function __construct(private readonly Metadata_Factory_Interface $metadata_factory)
    {
    }
    public function guess_type(string $class, string $property): ?Type_Guess
    {
        return $this->guess($class, $property, $this->guess_type_for_constraint(...));
    }
    public function guess_required(string $class, string $property): ?Value_Guess
    {
        // If we don't find any constraint telling otherwise, we can assume
        // that a field is not required (with LOW_CONFIDENCE)
        return $this->guess($class, $property, $this->guess_required_for_constraint(...), false);
    }
    public function guess_max_length(string $class, string $property): ?Value_Guess
    {
        return $this->guess($class, $property, $this->guess_max_length_for_constraint(...));
    }
    public function guess_pattern(string $class, string $property): ?Value_Guess
    {
        return $this->guess($class, $property, $this->guess_pattern_for_constraint(...));
    }
    /**
     * Guesses a field class name for a given constraint.
     */
    public function guess_type_for_constraint(Constraint $constraint): ?Type_Guess
    {
        switch ($constraint::class) {
            case Type::class:
                switch ($constraint->type) {
                    case 'array':
                        return new Type_Guess(Collection_Type::class, [], Guess::MEDIUM_CONFIDENCE);
                    case 'boolean':
                    case 'bool':
                        return new Type_Guess(Checkbox_Type::class, [], Guess::MEDIUM_CONFIDENCE);
                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new Type_Guess(Number_Type::class, [], Guess::MEDIUM_CONFIDENCE);
                    case 'integer':
                    case 'int':
                    case 'long':
                        return new Type_Guess(Integer_Type::class, [], Guess::MEDIUM_CONFIDENCE);
                    case \DateTime::class:
                    case '\DateTime':
                        return new Type_Guess(Date_Type::class, [], Guess::MEDIUM_CONFIDENCE);
                    case \DateTimeImmutable::class:
                    case '\DateTimeImmutable':
                    case \DateTimeInterface::class:
                    case '\DateTimeInterface':
                        return new Type_Guess(Date_Type::class, ['input' => 'datetime_immutable'], Guess::MEDIUM_CONFIDENCE);
                    case 'string':
                        return new Type_Guess(Text_Type::class, [], Guess::LOW_CONFIDENCE);
                }
                break;
            case Country::class:
                return new Type_Guess(Country_Type::class, [], Guess::HIGH_CONFIDENCE);
            case Currency::class:
                return new Type_Guess(Currency_Type::class, [], Guess::HIGH_CONFIDENCE);
            case Date::class:
                return new Type_Guess(Date_Type::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);
            case DateTime::class:
                return new Type_Guess(Date_Time_Type::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);
            case Email::class:
                return new Type_Guess(Email_Type::class, [], Guess::HIGH_CONFIDENCE);
            case File::class:
            case Image::class:
                $options = [];
                if ($constraint->mime_types) {
                    $options = ['attr' => ['accept' => implode(',', (array) $constraint->mime_types)]];
                }
                return new Type_Guess(File_Type::class, $options, Guess::HIGH_CONFIDENCE);
            case Language::class:
                return new Type_Guess(Language_Type::class, [], Guess::HIGH_CONFIDENCE);
            case Locale::class:
                return new Type_Guess(Locale_Type::class, [], Guess::HIGH_CONFIDENCE);
            case Time::class:
                return new Type_Guess(Time_Type::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);
            case Url::class:
                return new Type_Guess(Url_Type::class, [], Guess::HIGH_CONFIDENCE);
            case Ip::class:
                return new Type_Guess(Text_Type::class, [], Guess::MEDIUM_CONFIDENCE);
            case Length::class:
            case Regex::class:
                return new Type_Guess(Text_Type::class, [], Guess::LOW_CONFIDENCE);
            case Range::class:
                return new Type_Guess(Number_Type::class, [], Guess::LOW_CONFIDENCE);
            case Count::class:
                return new Type_Guess(Collection_Type::class, [], Guess::LOW_CONFIDENCE);
            case Is_True::class:
            case Is_False::class:
                return new Type_Guess(Checkbox_Type::class, [], Guess::MEDIUM_CONFIDENCE);
        }
        return null;
    }
    /**
     * Guesses whether a field is required based on the given constraint.
     */
    public function guess_required_for_constraint(Constraint $constraint): ?Value_Guess
    {
        return match ($constraint::class) {
            Not_Null::class, Not_Blank::class, Is_True::class => new Value_Guess(true, Guess::HIGH_CONFIDENCE),
            default => null,
        };
    }
    /**
     * Guesses a field's maximum length based on the given constraint.
     */
    public function guess_max_length_for_constraint(Constraint $constraint): ?Value_Guess
    {
        switch ($constraint::class) {
            case Length::class:
                if (is_numeric($constraint->max)) {
                    return new Value_Guess($constraint->max, Guess::HIGH_CONFIDENCE);
                }
                break;
            case Type::class:
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'], true)) {
                    return new Value_Guess(null, Guess::MEDIUM_CONFIDENCE);
                }
                break;
            case Range::class:
                if (is_numeric($constraint->max)) {
                    return new Value_Guess(\strlen((string) $constraint->max), Guess::LOW_CONFIDENCE);
                }
                break;
        }
        return null;
    }
    /**
     * Guesses a field's pattern based on the given constraint.
     */
    public function guess_pattern_for_constraint(Constraint $constraint): ?Value_Guess
    {
        switch ($constraint::class) {
            case Length::class:
                if (is_numeric($constraint->min)) {
                    return new Value_Guess(\sprintf('.{%s,}', (string) $constraint->min), Guess::LOW_CONFIDENCE);
                }
                break;
            case Regex::class:
                $html_pattern = $constraint->get_html_pattern();
                if (null !== $html_pattern) {
                    return new Value_Guess($html_pattern, Guess::HIGH_CONFIDENCE);
                }
                break;
            case Range::class:
                if (is_numeric($constraint->min)) {
                    return new Value_Guess(\sprintf('.{%s,}', \strlen((string) $constraint->min)), Guess::LOW_CONFIDENCE);
                }
                break;
            case Type::class:
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'], true)) {
                    return new Value_Guess(null, Guess::MEDIUM_CONFIDENCE);
                }
                break;
        }
        return null;
    }
    /**
     * Iterates over the constraints of a property, executes a constraints on
     * them and returns the best guess.
     *
     * @param \Closure $closure      The closure that returns a guess
     *                               for a given constraint
     * @param mixed    $defaultValue The default value assumed if no other value
     *                               can be guessed
     */
    protected function guess(string $class, string $property, \Closure $closure, mixed $default_value = null): ?Guess
    {
        $guesses = [];
        $class_metadata = $this->metadata_factory->get_metadata_for($class);
        if ($class_metadata instanceof Class_Metadata_Interface && $class_metadata->has_property_metadata($property)) {
            foreach ($class_metadata->get_property_metadata($property) as $member_metadata) {
                foreach ($member_metadata->get_constraints() as $constraint) {
                    if ($guess = $closure($constraint)) {
                        $guesses[] = $guess;
                    }
                }
            }
        }
        if (null !== $default_value) {
            $guesses[] = new Value_Guess($default_value, Guess::LOW_CONFIDENCE);
        }
        return Guess::get_best_guess($guesses);
    }
}