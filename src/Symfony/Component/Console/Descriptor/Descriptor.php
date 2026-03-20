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
namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Descriptor implements Descriptor_Interface
{
    protected Output_Interface $output;
    public function describe(Output_Interface $output, object $object, array $options = []): void
    {
        $this->output = $output;
        match (true) {
            $object instanceof Input_Argument => $this->describe_input_argument($object, $options),
            $object instanceof Input_Option => $this->describe_input_option($object, $options),
            $object instanceof Input_Definition => $this->describe_input_definition($object, $options),
            $object instanceof Command => $this->describe_command($object, $options),
            $object instanceof Application => $this->describe_application($object, $options),
            default => throw new InvalidArgumentException(\sprintf('Object of type "%s" is not describable.', get_debug_type($object))),
        };
    }
    protected function write(string $content, bool $decorated = false): void
    {
        $this->output->write($content, false, $decorated ? Output_Interface::OUTPUT_NORMAL : Output_Interface::OUTPUT_RAW);
    }
    /**
     * Describes an InputArgument instance.
     */
    abstract protected function describe_input_argument(Input_Argument $argument, array $options = []): void;
    /**
     * Describes an InputOption instance.
     */
    abstract protected function describe_input_option(Input_Option $option, array $options = []): void;
    /**
     * Describes an InputDefinition instance.
     */
    abstract protected function describe_input_definition(Input_Definition $definition, array $options = []): void;
    /**
     * Describes a Command instance.
     */
    abstract protected function describe_command(Command $command, array $options = []): void;
    /**
     * Describes an Application instance.
     */
    abstract protected function describe_application(Application $application, array $options = []): void;
}