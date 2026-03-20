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

use Symfony\Component\Serializer\Serializer_Interface;
use Twig\Extension\Runtime_Extension_Interface;
/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
final readonly class Serializer_Runtime implements Runtime_Extension_Interface
{
    public function __construct(private Serializer_Interface $serializer)
    {
    }
    public function serialize(mixed $data, string $format = 'json', array $context = []): string
    {
        return $this->serializer->serialize($data, $format, $context);
    }
}