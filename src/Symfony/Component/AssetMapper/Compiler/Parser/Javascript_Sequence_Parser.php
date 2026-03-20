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
namespace Symfony\Component\Asset_Mapper\Compiler\Parser;

/**
 * Parses JavaScript content to identify sequences of strings, comments, etc.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class Javascript_Sequence_Parser
{
    private const STATE_DEFAULT = 0;
    private const STATE_COMMENT = 1;
    private const STATE_STRING = 2;
    private int $cursor = 0;
    private readonly int $content_end;
    private string $pattern;
    private int $current_sequence_type = self::STATE_DEFAULT;
    private ?int $current_sequence_end = null;
    private const COMMENT_SEPARATORS = [
        '/*',
        // Multi-line comment
        '//',
        // Single-line comment
        '"',
        // Double quote
        '\'',
        // Single quote
        '`',
    ];
    public function __construct(private readonly string $content)
    {
        $this->content_end = \strlen($content);
        $this->pattern ??= '/' . implode('|', array_map(static fn(string $ch): string => preg_quote($ch, '/'), self::COMMENT_SEPARATORS)) . '/';
    }
    public function is_string(): bool
    {
        return self::STATE_STRING === $this->current_sequence_type;
    }
    public function is_executable(): bool
    {
        return self::STATE_DEFAULT === $this->current_sequence_type;
    }
    public function is_comment(): bool
    {
        return self::STATE_COMMENT === $this->current_sequence_type;
    }
    public function parse_until(int $position): void
    {
        if ($position > $this->content_end) {
            throw new \RuntimeException('Cannot parse beyond the end of the content.');
        }
        if ($position < $this->cursor) {
            throw new \RuntimeException('Cannot parse backwards.');
        }
        while ($this->cursor <= $position) {
            // Current CodeSequence ?
            if (null !== $this->current_sequence_end) {
                if ($this->current_sequence_end > $position) {
                    $this->cursor = $position;
                    return;
                }
                $this->cursor = $this->current_sequence_end;
                $this->set_sequence(self::STATE_DEFAULT);
            }
            preg_match($this->pattern, $this->content, $matches, \PREG_OFFSET_CAPTURE, $this->cursor);
            if (!$matches) {
                $this->ends_with_sequence(self::STATE_DEFAULT, $position);
                return;
            }
            $match_pos = $matches[0][1];
            $match_char = $matches[0][0];
            if ($match_pos > $position) {
                $this->set_sequence(self::STATE_DEFAULT, $match_pos - 1);
                $this->cursor = $position;
                return;
            }
            // Multi-line comment
            if ('/*' === $match_char) {
                if (false === $end_pos = strpos($this->content, '*/', $match_pos + 2)) {
                    $this->ends_with_sequence(self::STATE_COMMENT, $position);
                    return;
                }
                $this->cursor = min($end_pos + 2, $position);
                $this->set_sequence(self::STATE_COMMENT, $end_pos + 2);
                continue;
            }
            // Single-line comment
            if ('//' === $match_char) {
                if (false === $end_pos = strpos($this->content, "\n", $match_pos + 2)) {
                    $this->ends_with_sequence(self::STATE_COMMENT, $position);
                    return;
                }
                $this->cursor = min($end_pos + 1, $position);
                $this->set_sequence(self::STATE_COMMENT, $end_pos + 1);
                continue;
            }
            if ('"' === $match_char || "'" === $match_char || '`' === $match_char) {
                $end_pos = $match_pos + 1;
                while (false !== $end_pos = strpos($this->content, $match_char, $end_pos)) {
                    $backslashes = 0;
                    $i = $end_pos - 1;
                    while ($i >= 0 && '\\' === $this->content[$i]) {
                        ++$backslashes;
                        --$i;
                    }
                    if (0 === $backslashes % 2) {
                        break;
                    }
                    ++$end_pos;
                }
                if (false === $end_pos) {
                    $this->ends_with_sequence(self::STATE_STRING, $position);
                    return;
                }
                $this->cursor = min($end_pos + 1, $position);
                $this->set_sequence(self::STATE_STRING, $end_pos + 1);
                continue;
            }
            // Fallback
            $this->cursor = $match_pos + 1;
        }
    }
    /**
     * @param int<self::STATE_*> $type
     */
    private function ends_with_sequence(int $type, int $cursor): void
    {
        $this->cursor = $cursor;
        $this->current_sequence_type = $type;
        $this->current_sequence_end = $this->content_end;
    }
    /**
     * @param int<self::STATE_*> $type
     */
    private function set_sequence(int $type, ?int $end = null): void
    {
        $this->current_sequence_type = $type;
        $this->current_sequence_end = $end;
    }
}