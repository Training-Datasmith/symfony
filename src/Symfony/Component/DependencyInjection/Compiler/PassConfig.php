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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
/**
 * Compiler Pass Configuration.
 *
 * This class has a default configuration embedded.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Pass_Config
{
    // In the order of execution
    public const TYPE_BEFORE_OPTIMIZATION = 'beforeOptimization';
    public const TYPE_OPTIMIZE = 'optimization';
    public const TYPE_BEFORE_REMOVING = 'beforeRemoving';
    public const TYPE_REMOVE = 'removing';
    public const TYPE_AFTER_REMOVING = 'afterRemoving';
    private Merge_Extension_Configuration_Pass $merge_pass;
    private array $after_removing_passes;
    private array $before_optimization_passes;
    private array $before_removing_passes = [];
    private array $optimization_passes;
    private array $removing_passes;
    public function __construct()
    {
        $this->merge_pass = new Merge_Extension_Configuration_Pass();
        $this->before_optimization_passes = [100 => [new Resolve_Class_Pass(), new Register_Autoconfigure_Attributes_Pass(), new Autowire_As_Decorator_Pass(), new Attribute_Autoconfiguration_Pass(), new Resolve_Instanceof_Conditionals_Pass(), new Register_Env_Var_Processors_Pass()], -1000 => [new Extension_Compiler_Pass()]];
        $this->optimization_passes = [[new Auto_Alias_Service_Pass(), new Validate_Env_Placeholders_Pass(), new Resolve_Decorator_Stack_Pass(), new Resolve_Autowire_Inline_Attributes_Pass(), new Resolve_Child_Definitions_Pass(), new Register_Service_Subscribers_Pass(), new Resolve_Parameter_Place_Holders_Pass(false, false), new Resolve_Factory_Class_Pass(), new Resolve_Named_Arguments_Pass(), new Autowire_Required_Methods_Pass(), new Autowire_Required_Properties_Pass(), new Resolve_Bindings_Pass(), new Service_Locator_Tag_Pass(), new Tag_Decorator_Pass(), new Decorator_Service_Pass(), new Check_Definition_Validity_Pass(), new Autowire_Pass(false), new Service_Locator_Tag_Pass(), new Resolve_Tagged_Iterator_Argument_Pass(), new Resolve_Service_Subscribers_Pass(), new Resolve_References_To_Aliases_Pass(), new Resolve_Invalid_References_Pass(), new Analyze_Service_References_Pass(true), new Check_Circular_References_Pass(), new Check_Reference_Validity_Pass(), new Check_Arguments_Validity_Pass(false)]];
        $this->removing_passes = [[new Remove_Private_Aliases_Pass(), new Replace_Alias_By_Actual_Definition_Pass(), new Remove_Abstract_Definitions_Pass(), new Remove_Unused_Definitions_Pass(), new Analyze_Service_References_Pass(), new Check_Exception_On_Invalid_Reference_Behavior_Pass(), new Inline_Service_Definitions_Pass(new Analyze_Service_References_Pass()), new Analyze_Service_References_Pass(), new Definition_Error_Exception_Pass()]];
        $this->after_removing_passes = [
            0 => [new Resolve_Hot_Path_Pass(), new Resolve_No_Preload_Pass(), new Alias_Deprecated_Public_Services_Pass()],
            // Let build parameters be available as late as possible
            // Don't remove array parameters since ResolveParameterPlaceHoldersPass doesn't resolve them
            -2048 => [new Remove_Build_Parameters_Pass(true)],
        ];
    }
    /**
     * Returns all passes in order to be processed.
     *
     * @return CompilerPassInterface[]
     */
    public function get_passes(): array
    {
        return array_merge([$this->merge_pass], $this->get_before_optimization_passes(), $this->get_optimization_passes(), $this->get_before_removing_passes(), $this->get_removing_passes(), $this->get_after_removing_passes());
    }
    /**
     * Adds a pass.
     *
     * @throws InvalidArgumentException when a pass type doesn't exist
     */
    public function add_pass(Compiler_Pass_Interface $pass, string $type = self::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): void
    {
        $property = $type . 'Passes';
        if (!isset($this->{$property})) {
            throw new InvalidArgumentException(\sprintf('Invalid type "%s".', $type));
        }
        $passes =& $this->{$property};
        if (!isset($passes[$priority])) {
            $passes[$priority] = [];
        }
        $passes[$priority][] = $pass;
    }
    /**
     * Gets all passes for the AfterRemoving pass.
     *
     * @return CompilerPassInterface[]
     */
    public function get_after_removing_passes(): array
    {
        return $this->sort_passes($this->after_removing_passes);
    }
    /**
     * Gets all passes for the BeforeOptimization pass.
     *
     * @return CompilerPassInterface[]
     */
    public function get_before_optimization_passes(): array
    {
        return $this->sort_passes($this->before_optimization_passes);
    }
    /**
     * Gets all passes for the BeforeRemoving pass.
     *
     * @return CompilerPassInterface[]
     */
    public function get_before_removing_passes(): array
    {
        return $this->sort_passes($this->before_removing_passes);
    }
    /**
     * Gets all passes for the Optimization pass.
     *
     * @return CompilerPassInterface[]
     */
    public function get_optimization_passes(): array
    {
        return $this->sort_passes($this->optimization_passes);
    }
    /**
     * Gets all passes for the Removing pass.
     *
     * @return CompilerPassInterface[]
     */
    public function get_removing_passes(): array
    {
        return $this->sort_passes($this->removing_passes);
    }
    /**
     * Gets the Merge pass.
     */
    public function get_merge_pass(): Compiler_Pass_Interface
    {
        return $this->merge_pass;
    }
    public function set_merge_pass(Compiler_Pass_Interface $pass): void
    {
        $this->merge_pass = $pass;
    }
    /**
     * Sets the AfterRemoving passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function set_after_removing_passes(array $passes): void
    {
        $this->after_removing_passes = [$passes];
    }
    /**
     * Sets the BeforeOptimization passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function set_before_optimization_passes(array $passes): void
    {
        $this->before_optimization_passes = [$passes];
    }
    /**
     * Sets the BeforeRemoving passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function set_before_removing_passes(array $passes): void
    {
        $this->before_removing_passes = [$passes];
    }
    /**
     * Sets the Optimization passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function set_optimization_passes(array $passes): void
    {
        $this->optimization_passes = [$passes];
    }
    /**
     * Sets the Removing passes.
     *
     * @param CompilerPassInterface[] $passes
     */
    public function set_removing_passes(array $passes): void
    {
        $this->removing_passes = [$passes];
    }
    /**
     * Sort passes by priority.
     *
     * @param array $passes CompilerPassInterface instances with their priority as key
     *
     * @return CompilerPassInterface[]
     */
    private function sort_passes(array $passes): array
    {
        if (0 === \count($passes)) {
            return [];
        }
        krsort($passes);
        // Flatten the array
        return array_merge(...$passes);
    }
}