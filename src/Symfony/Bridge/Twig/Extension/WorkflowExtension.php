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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Transition_Blocker_List;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * WorkflowExtension.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Carlos Pereira De Amorim <carlos@shauri.fr>
 */
final class Workflow_Extension extends Abstract_Extension
{
    public function __construct(private readonly Registry $workflow_registry)
    {
    }
    public function get_functions(): array
    {
        return [new Twig_Function('workflow_can', $this->can_transition(...)), new Twig_Function('workflow_transitions', $this->get_enabled_transitions(...)), new Twig_Function('workflow_transition', $this->get_enabled_transition(...)), new Twig_Function('workflow_has_marked_place', $this->has_marked_place(...)), new Twig_Function('workflow_marked_places', $this->get_marked_places(...)), new Twig_Function('workflow_metadata', $this->get_metadata(...)), new Twig_Function('workflow_transition_blockers', $this->build_transition_blocker_list(...))];
    }
    /**
     * Returns true if the transition is enabled.
     */
    public function can_transition(object $subject, string $transition_name, ?string $name = null): bool
    {
        return $this->workflow_registry->get($subject, $name)->can($subject, $transition_name);
    }
    /**
     * Returns all enabled transitions.
     *
     * @return Transition[]
     */
    public function get_enabled_transitions(object $subject, ?string $name = null): array
    {
        return $this->workflow_registry->get($subject, $name)->get_enabled_transitions($subject);
    }
    public function get_enabled_transition(object $subject, string $transition, ?string $name = null): ?Transition
    {
        return $this->workflow_registry->get($subject, $name)->get_enabled_transition($subject, $transition);
    }
    /**
     * Returns true if the place is marked.
     */
    public function has_marked_place(object $subject, string $place_name, ?string $name = null): bool
    {
        return $this->workflow_registry->get($subject, $name)->get_marking($subject)->has($place_name);
    }
    /**
     * Returns marked places.
     *
     * @return string[]|int[]
     */
    public function get_marked_places(object $subject, bool $places_name_only = true, ?string $name = null): array
    {
        $places = $this->workflow_registry->get($subject, $name)->get_marking($subject)->get_places();
        if ($places_name_only) {
            return array_keys($places);
        }
        return $places;
    }
    /**
     * Returns the metadata for a specific subject.
     *
     * @param string|Transition|null $metadataSubject Use null to get workflow metadata
     *                                                Use a string (the place name) to get place metadata
     *                                                Use a Transition instance to get transition metadata
     */
    public function get_metadata(object $subject, string $key, string|Transition|null $metadata_subject = null, ?string $name = null): mixed
    {
        return $this->workflow_registry->get($subject, $name)->get_metadata_store()->get_metadata($key, $metadata_subject);
    }
    public function build_transition_blocker_list(object $subject, string $transition_name, ?string $name = null): Transition_Blocker_List
    {
        $workflow = $this->workflow_registry->get($subject, $name);
        return $workflow->build_transition_blocker_list($subject, $transition_name);
    }
}