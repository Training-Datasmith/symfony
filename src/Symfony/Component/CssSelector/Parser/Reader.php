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
namespace Symfony\Component\Css_Selector\Parser;

/**
 * CSS selector reader.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Reader
{
    private readonly int $length;
    private int $position = 0;
    public function __construct(private readonly string $source)
    {
        $this->length = \strlen($source);
    }
    public function is_eof(): bool
    {
        return $this->position >= $this->length;
    }
    public function get_position(): int
    {
        return $this->position;
    }
    public function get_remaining_length(): int
    {
        return $this->length - $this->position;
    }
    public function get_substring(int $length, int $offset = 0): string
    {
        return substr($this->source, $this->position + $offset, $length);
    }
    public function get_offset(string $string): int|false
    {
        $position = strpos($this->source, $string, $this->position);
        return false === $position ? false : $position - $this->position;
    }
    public function find_pattern(string $pattern): array|false
    {
        $source = substr($this->source, $this->position);
        if (preg_match($pattern, $source, $matches)) {
            return $matches;
        }
        return false;
    }
    public function move_forward(int $length): void
    {
        $this->position += $length;
    }
    public function move_to_end(): void
    {
        $this->position = $this->length;
    }
}