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

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Provides a dummy Normalizer which extends the AbstractNormalizer.
 *
 * @author Konstantin S. M. Möllers <ksm.moellers@gmail.com>
 */
class AbstractNormalizerDummy extends AbstractNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function getAllowedAttributes(string|object $classOrObject, array $context, bool $attributesAsString = false): array|bool
    {
        return parent::getAllowedAttributes($classOrObject, $context, $attributesAsString);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return true;
    }
}
