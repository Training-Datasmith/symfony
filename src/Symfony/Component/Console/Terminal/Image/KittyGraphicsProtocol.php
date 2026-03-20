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
 * Handles the Kitty Graphics Protocol for terminal image paste/display.
 *
 * The Kitty Graphics Protocol uses Application Programming Command (APC) sequences:
 * - Start: ESC _ G (0x1B 0x5F 0x47)
 * - End: ESC \ (0x1B 0x5C) - also known as ST (String Terminator)
 *
 * Format: ESC_G<control data>;<payload>ESC\
 *
 * @see https://sw.kovidgoyal.net/kitty/graphics-protocol/
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class Kitty_Graphics_Protocol implements Image_Protocol_Interface
{
    public const APC_START = "\x1b_G";
    public const ST = "\x1b\\";
    public function detect_pasted_image(string $data): bool
    {
        return str_contains($data, self::APC_START);
    }
    public function decode(string $data): array
    {
        if (false === $start = strpos($data, self::APC_START)) {
            return ['data' => '', 'format' => null];
        }
        if (false === $end = strpos($data, self::ST, $start)) {
            $end = strpos($data, "\x07", $start);
        }
        if (false === $end) {
            return ['data' => '', 'format' => null];
        }
        $content = substr($data, $start + \strlen(self::APC_START), $end - $start - \strlen(self::APC_START));
        if (false === $semicolon_pos = strpos($content, ';')) {
            return ['data' => '', 'format' => null];
        }
        $control_data = substr($content, 0, $semicolon_pos);
        $payload = substr($content, $semicolon_pos + 1);
        $decoded_data = base64_decode($payload, true);
        if (false === $decoded_data) {
            return ['data' => '', 'format' => null];
        }
        return ['data' => $decoded_data, 'format' => $this->parse_format($control_data)];
    }
    public function encode(string $image_data, ?int $max_width = null): string
    {
        $format = $this->detect_image_format($image_data);
        $control_parts = ['a=T', 'f=100'];
        if (null !== $max_width) {
            $control_parts[] = \sprintf('c=%d', $max_width);
        }
        if ('png' === $format) {
            $control_parts[1] = 'f=100';
        }
        $control_data = implode(',', $control_parts);
        $payload = base64_encode($image_data);
        $max_chunk_size = 4096;
        if (\strlen($payload) <= $max_chunk_size) {
            return self::APC_START . $control_data . ';' . $payload . self::ST;
        }
        $chunks = str_split($payload, $max_chunk_size);
        $result = '';
        foreach ($chunks as $i => $chunk) {
            $is_last = $i === \count($chunks) - 1;
            $chunk_control = $i > 0 ? 'm=' . ($is_last ? '0' : '1') : $control_data . ',m=' . ($is_last ? '0' : '1');
            $result .= self::APC_START . $chunk_control . ';' . $chunk . self::ST;
        }
        return $result;
    }
    public function get_name(): string
    {
        return 'kitty';
    }
    private function parse_format(string $control_data): ?string
    {
        foreach (explode(',', $control_data) as $pair) {
            $parts = explode('=', $pair, 2);
            if (2 === \count($parts) && 'f' === $parts[0]) {
                return match ($parts[1]) {
                    '24' => 'rgb',
                    '32' => 'rgba',
                    '100' => 'png',
                    default => null,
                };
            }
        }
        return null;
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