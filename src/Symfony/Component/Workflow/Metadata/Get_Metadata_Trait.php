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
trait GetMetadataTrait
{
    public function getMetadata(string $key, string|Transition|null $subject = null): mixed
    {
        if (null === $subject) {
            return $this->getWorkflowMetadata()[$key] ?? null;
        }

        $metadataBag = \is_string($subject) ? $this->getPlaceMetadata($subject) : $this->getTransitionMetadata($subject);

        return $metadataBag[$key] ?? null;
    }
}
