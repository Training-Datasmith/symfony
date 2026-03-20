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
namespace Symfony\Component\Console\Question;

use Symfony\Component\Console\Exception\InvalidArgumentException;
/**
 * Represents a question that accepts file input (paste or path).
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class File_Question extends Question
{
    public function __construct(string $question, private readonly bool $allow_paste = true, private readonly bool $allow_path = true)
    {
        parent::__construct($question);
        if (!$allow_paste && !$allow_path) {
            throw new InvalidArgumentException('At least one of allowPaste or allowPath must be true.');
        }
        $this->set_trimmable(false);
    }
    public function is_paste_allowed(): bool
    {
        return $this->allow_paste;
    }
    public function is_path_allowed(): bool
    {
        return $this->allow_path;
    }
}