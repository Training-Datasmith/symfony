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
namespace Symfony\Component\Dependency_Injection\Argument;

/**
 * Represents an abstract service argument, which have to be set by a compiler pass or a DI extension.
 */
final class Abstract_Argument
{
    use Argument_Trait;
    private string $text;
    private string $context = '';
    public function __construct(string $text = '')
    {
        $this->text = trim($text, '. ');
    }
    public function set_context(string $context): void
    {
        $this->context = $context . ' is abstract' . ('' === $this->text ? '' : ': ');
    }
    public function get_text(): string
    {
        return $this->text;
    }
    public function get_text_with_context(): string
    {
        return $this->context . $this->text . '.';
    }
}