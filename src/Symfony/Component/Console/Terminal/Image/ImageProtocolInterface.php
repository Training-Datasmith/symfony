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
namespace Symfony\Component\Console\Terminal\Image;

/**
 * Contract for terminal image protocol handlers.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
interface Image_Protocol_Interface
{
    public function detect_pasted_image(string $data): bool;
    /**
     * @return array{data: string, format: string|null}
     */
    public function decode(string $data): array;
    public function encode(string $image_data, ?int $max_width = null): string;
    public function get_name(): string;
}