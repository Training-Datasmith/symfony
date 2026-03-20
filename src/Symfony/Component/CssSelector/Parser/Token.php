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
 * CSS selector token.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Token implements \Stringable
{
    public const TYPE_FILE_END = 'eof';
    public const TYPE_DELIMITER = 'delimiter';
    public const TYPE_WHITESPACE = 'whitespace';
    public const TYPE_IDENTIFIER = 'identifier';
    public const TYPE_HASH = 'hash';
    public const TYPE_NUMBER = 'number';
    public const TYPE_STRING = 'string';
    /**
     * @param self::TYPE_*|null $type
     */
    public function __construct(private readonly ?string $type, private readonly ?string $value, private readonly ?int $position)
    {
    }
    /**
     * @return self::TYPE_*|null
     */
    public function get_type(): ?string
    {
        return $this->type;
    }
    public function get_value(): ?string
    {
        return $this->value;
    }
    public function get_position(): ?int
    {
        return $this->position;
    }
    public function is_file_end(): bool
    {
        return self::TYPE_FILE_END === $this->type;
    }
    public function is_delimiter(array $values = []): bool
    {
        if (self::TYPE_DELIMITER !== $this->type) {
            return false;
        }
        if (!$values) {
            return true;
        }
        return \in_array($this->value, $values, true);
    }
    public function is_whitespace(): bool
    {
        return self::TYPE_WHITESPACE === $this->type;
    }
    public function is_identifier(): bool
    {
        return self::TYPE_IDENTIFIER === $this->type;
    }
    public function is_hash(): bool
    {
        return self::TYPE_HASH === $this->type;
    }
    public function is_number(): bool
    {
        return self::TYPE_NUMBER === $this->type;
    }
    public function is_string(): bool
    {
        return self::TYPE_STRING === $this->type;
    }
    public function __toString(): string
    {
        if ($this->value) {
            return \sprintf('<%s "%s" at %s>', $this->type, $this->value, $this->position);
        }
        return \sprintf('<%s at %s>', $this->type, $this->position);
    }
}