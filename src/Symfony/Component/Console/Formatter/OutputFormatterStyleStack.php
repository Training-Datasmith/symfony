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
namespace Symfony\Component\Console\Formatter;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Output_Formatter_Style_Stack implements Reset_Interface
{
    /**
     * @var OutputFormatterStyleInterface[]
     */
    private array $styles = [];
    public function __construct(private ?Output_Formatter_Style_Interface $empty_style = new Output_Formatter_Style())
    {
        $this->reset();
    }
    /**
     * Resets stack (ie. empty internal arrays).
     */
    public function reset(): void
    {
        $this->styles = [];
    }
    /**
     * Pushes a style in the stack.
     */
    public function push(Output_Formatter_Style_Interface $style): void
    {
        $this->styles[] = $style;
    }
    /**
     * Pops a style from the stack.
     *
     * @throws InvalidArgumentException When style tags incorrectly nested
     */
    public function pop(?Output_Formatter_Style_Interface $style = null): Output_Formatter_Style_Interface
    {
        if (!$this->styles) {
            return $this->empty_style;
        }
        if (null === $style) {
            return array_pop($this->styles);
        }
        foreach (array_reverse($this->styles, true) as $index => $stacked_style) {
            if ($style->apply('') === $stacked_style->apply('')) {
                $this->styles = \array_slice($this->styles, 0, $index);
                return $stacked_style;
            }
        }
        throw new InvalidArgumentException('Incorrectly nested style tag found.');
    }
    /**
     * Computes current style with stacks top codes.
     */
    public function get_current(): Output_Formatter_Style_Interface
    {
        if (!$this->styles) {
            return $this->empty_style;
        }
        return $this->styles[\count($this->styles) - 1];
    }
    /**
     * @return $this
     */
    public function set_empty_style(Output_Formatter_Style_Interface $empty_style): static
    {
        $this->empty_style = $empty_style;
        return $this;
    }
    public function get_empty_style(): Output_Formatter_Style_Interface
    {
        return $this->empty_style;
    }
}