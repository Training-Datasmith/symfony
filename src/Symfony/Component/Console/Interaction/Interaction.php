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
namespace Symfony\Component\Console\Interaction;

use Symfony\Component\Console\Attribute\Interactive_Attribute_Interface;
use Symfony\Component\Console\Attribute\Map_Input;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @internal
 */
final readonly class Interaction
{
    public function __construct(private object $owner, private Interactive_Attribute_Interface $attribute)
    {
    }
    /**
     * @param \Closure(\ReflectionFunction $function, InputInterface $input, OutputInterface $output): array $parameterResolver
     */
    public function interact(Input_Interface $input, Output_Interface $output, \Closure $parameter_resolver): void
    {
        if ($this->owner instanceof Map_Input) {
            $function = $this->attribute->get_function($this->owner->create_instance($input));
            $function->invoke(...$parameter_resolver($function, $input, $output));
            $this->owner->set_value($input, $function->get_closure_this());
            return;
        }
        $function = $this->attribute->get_function($this->owner);
        $function->invoke(...$args = $parameter_resolver($function, $input, $output));
        foreach ($function->get_parameters() as $i => $parameter) {
            if (\is_object($args[$i]) && $spec = Map_Input::try_from($parameter)) {
                $spec->set_value($input, $args[$i]);
            }
        }
    }
}