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
namespace Symfony\Component\Form\Extension\Core\Data_Transformer;

use Symfony\Component\Form\Data_Transformer_Interface;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
use Symfony\Component\Uid\Uuid;
/**
 * Transforms between a UUID string and a Uuid object.
 *
 * @author Pavel Dyakonov <wapinet@mail.ru>
 *
 * @implements DataTransformerInterface<Uuid, string>
 */
class Uuid_To_String_Transformer implements Data_Transformer_Interface
{
    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof Uuid) {
            throw new Transformation_Failed_Exception('Expected a Uuid.');
        }
        return (string) $value;
    }
    public function reverse_transform(mixed $value): ?Uuid
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if (!Uuid::is_valid($value)) {
            throw new Transformation_Failed_Exception(\sprintf('The value "%s" is not a valid UUID.', $value));
        }
        try {
            return Uuid::from_string($value);
        } catch (\InvalidArgumentException $e) {
            throw new Transformation_Failed_Exception(\sprintf('The value "%s" is not a valid UUID.', $value), $e->get_code(), $e);
        }
    }
}