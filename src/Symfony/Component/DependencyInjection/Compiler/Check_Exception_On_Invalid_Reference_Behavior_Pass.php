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
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Checks that all references are pointing to a valid service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Check_Exception_On_Invalid_Reference_Behavior_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $service_locator_context_ids = [];
    public function process(Container_Builder $container): void
    {
        $this->service_locator_context_ids = [];
        foreach ($container->find_tagged_service_ids('container.service_locator_context') as $id => $tags) {
            $this->service_locator_context_ids[$id] = $tags[0]['id'];
            $container->get_definition($id)->clear_tag('container.service_locator_context');
        }
        try {
            parent::process($container);
        } finally {
            $this->service_locator_context_ids = [];
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Reference) {
            return parent::process_value($value, $is_root);
        }
        if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE < $value->get_invalid_behavior() || $this->container->has((string) $value)) {
            return $value;
        }
        $current_id = $this->current_id;
        $graph = $this->container->get_compiler()->get_service_reference_graph();
        if (isset($this->service_locator_context_ids[$current_id])) {
            $current_id = $this->service_locator_context_ids[$current_id];
            $locator = $this->container->get_definition($this->current_id)->get_factory()[0];
            $this->throw_service_not_found_exception($value, $current_id, $locator->get_argument(0));
        }
        if ('.' === $current_id[0] && $graph->has_node($current_id)) {
            foreach ($graph->get_node($current_id)->get_in_edges() as $edge) {
                if (!$edge->get_value() instanceof Reference) {
                    continue;
                }
                if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE < $edge->get_value()->get_invalid_behavior()) {
                    continue;
                }
                $source_id = $edge->get_source_node()->get_id();
                if ('.' !== $source_id[0]) {
                    $current_id = $source_id;
                    break;
                }
                if (isset($this->service_locator_context_ids[$source_id])) {
                    $current_id = $this->service_locator_context_ids[$source_id];
                    $locator = $this->container->get_definition($this->current_id);
                    $this->throw_service_not_found_exception($value, $current_id, $locator->get_argument(0));
                }
            }
        }
        $this->throw_service_not_found_exception($value, $current_id, $value);
    }
    private function throw_service_not_found_exception(Reference $ref, string $source_id, mixed $value): void
    {
        $id = (string) $ref;
        $alternatives = [];
        foreach ($this->container->get_service_ids() as $known_id) {
            if ('' === $known_id) {
                continue;
            }
            if ('.' === $known_id[0]) {
                continue;
            }
            if ($known_id === $this->current_id) {
                continue;
            }
            $lev = levenshtein($id, $known_id);
            if ($lev <= \strlen($id) / 3 || str_contains($known_id, $id)) {
                $alternatives[] = $known_id;
            }
        }
        $pass = new class extends Abstract_Recursive_Pass
        {
            public Reference $ref;
            public string $source_id;
            public array $alternatives;
            public function process_value(mixed $value, bool $is_root = false): mixed
            {
                if ($this->ref !== $value) {
                    return parent::process_value($value, $is_root);
                }
                $source_id = $this->source_id;
                if (null !== $this->current_id && $this->current_id !== (string) $value) {
                    $source_id = $this->current_id . '" in the container provided to "' . $source_id;
                }
                throw new Service_Not_Found_Exception((string) $value, $source_id, null, $this->alternatives);
            }
        };
        $pass->ref = $ref;
        $pass->source_id = $source_id;
        $pass->alternatives = $alternatives;
        $pass->process_value($value, true);
    }
}