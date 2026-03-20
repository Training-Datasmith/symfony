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

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Replaces aliases with actual service definitions, effectively removing these
 * aliases.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Replace_Alias_By_Actual_Definition_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $replacements;
    /**
     * Process the Container to replace aliases with service definitions.
     *
     * @throws InvalidArgumentException if the service definition does not exist
     */
    public function process(Container_Builder $container): void
    {
        // First collect all alias targets that need to be replaced
        $seen_alias_targets = [];
        $replacements = [];
        // Sort aliases so non-deprecated ones come first. This ensures that when
        // multiple aliases point to the same private definition, non-deprecated
        // aliases get priority for renaming. Otherwise, the definition might be
        // renamed to a deprecated alias ID, causing the original service ID to
        // become an alias to the deprecated one (inverting the alias chain).
        $aliases = $container->get_aliases();
        uasort($aliases, static fn($a, $b): int => $a->is_deprecated() <=> $b->is_deprecated());
        foreach ($aliases as $definition_id => $target) {
            $target_id = (string) $target;
            // Special case: leave this target alone
            if ('service_container' === $target_id) {
                continue;
            }
            // Check if target needs to be replaced
            if (isset($replacements[$target_id])) {
                $container->set_alias($definition_id, $replacements[$target_id])->set_public($target->is_public());
                if ($target->is_deprecated()) {
                    $container->get_alias($definition_id)->set_deprecated(...array_values($target->get_deprecation('%alias_id%')));
                }
            }
            // No need to process the same target twice
            if (isset($seen_alias_targets[$target_id])) {
                continue;
            }
            // Process new target
            $seen_alias_targets[$target_id] = true;
            try {
                $definition = $container->get_definition($target_id);
            } catch (Service_Not_Found_Exception $e) {
                if ('' !== $e->get_id() && '@' === $e->get_id()[0]) {
                    throw new Service_Not_Found_Exception($e->get_id(), $e->get_source_id(), null, [substr($e->get_id(), 1)]);
                }
                throw $e;
            }
            if ($definition->is_public()) {
                continue;
            }
            // Remove private definition and schedule for replacement
            $definition->set_public($target->is_public());
            $container->set_definition($definition_id, $definition);
            $container->remove_definition($target_id);
            $replacements[$target_id] = $definition_id;
            if ($target->is_public() && $target->is_deprecated()) {
                $definition->add_tag('container.private', $target->get_deprecation('%service_id%'));
            }
        }
        $this->replacements = $replacements;
        parent::process($container);
        $this->replacements = [];
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Reference && isset($this->replacements[$reference_id = (string) $value])) {
            // Perform the replacement
            $new_id = $this->replacements[$reference_id];
            $value = new Reference($new_id, $value->get_invalid_behavior());
            $this->container->log($this, \sprintf('Changed reference of service "%s" previously pointing to "%s" to "%s".', $this->current_id, $reference_id, $new_id));
        }
        return parent::process_value($value, $is_root);
    }
}