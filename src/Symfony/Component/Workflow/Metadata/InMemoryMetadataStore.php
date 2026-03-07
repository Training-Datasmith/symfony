<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Metadata;

use Symfony\Component\Workflow\Transition;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class InMemoryMetadataStore implements MetadataStoreInterface
{
    use GetMetadataTrait;

    /**
     * @param \SplObjectStorage<Transition, array>|null $transitionsMetadata
     */
    public function __construct(private array $workflowMetadata = [], private array $placesMetadata = [], private ?\SplObjectStorage $transitionsMetadata = new \SplObjectStorage())
    {
    }

    public function getWorkflowMetadata(): array
    {
        return $this->workflowMetadata;
    }

    public function getPlaceMetadata(string $place): array
    {
        return $this->placesMetadata[$place] ?? [];
    }

    public function getTransitionMetadata(Transition $transition): array
    {
        return $this->transitionsMetadata[$transition] ?? [];
    }
}
