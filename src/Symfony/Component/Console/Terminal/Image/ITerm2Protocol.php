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
 * Handles the iTerm2 Inline Images Protocol (OSC 1337).
 *
 * The iTerm2 protocol uses Operating System Command (OSC) sequences:
 * - Start: ESC ] 1337 ; File= (0x1B 0x5D 0x31 0x33 0x33 0x37 0x3B 0x46 0x69 0x6C 0x65 0x3D)
 * - End: BEL (0x07) or ESC \ (0x1B 0x5C)
 *
 * Format: ESC]1337;File=[arguments]:[base64 data]BEL
 *
 * @see https://iterm2.com/documentation-images.html
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class I_Term2protocol implements Image_Protocol_Interface
{
    public const OSC_START = "\x1b]1337;File=";
    public const BEL = "\x07";
    public const ST = "\x1b\\";
    public function detect_pasted_image(string $data): bool
    {
        return str_contains($data, self::OSC_START);
    }
    public function decode(string $data): array
    {
        if (false === $start = strpos($data, self::OSC_START)) {
            return ['data' => '', 'format' => null];
        }
        if (false === $end = strpos($data, self::BEL, $start)) {
            $end = strpos($data, self::ST, $start);
        }
        if (false === $end) {
            return ['data' => '', 'format' => null];
        }
        $content = substr($data, $start + \strlen(self::OSC_START), $end - $start - \strlen(self::OSC_START));
        if (false === $colon_pos = strpos($content, ':')) {
            return ['data' => '', 'format' => null];
        }
        $payload = substr($content, $colon_pos + 1);
        if (false === $decoded_data = base64_decode($payload, true)) {
            return ['data' => '', 'format' => null];
        }
        $format = $this->detect_image_format($decoded_data);
        return ['data' => $decoded_data, 'format' => $format];
    }
    public function encode(string $image_data, ?int $max_width = null): string
    {
        $arguments = ['inline=1'];
        if ($max_width) {
            $arguments[] = \sprintf('width=%d', $max_width);
        }
        $arguments[] = 'preserveAspectRatio=1';
        $argument_string = implode(';', $arguments);
        $payload = base64_encode($image_data);
        return self::OSC_START . $argument_string . ':' . $payload . self::BEL;
    }
    public function get_name(): string
    {
        return 'iterm2';
    }
    private function detect_image_format(string $data): ?string
    {
        return match (true) {
            str_starts_with($data, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($data, "\xff\xd8\xff") => 'jpg',
            str_starts_with($data, 'GIF87a'), str_starts_with($data, 'GIF89a') => 'gif',
            str_starts_with($data, 'RIFF') && 'WEBP' === substr($data, 8, 4) => 'webp',
            default => null,
        };
    }
}