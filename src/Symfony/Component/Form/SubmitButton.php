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
namespace Symfony\Component\Form;

/**
 * A button that submits the form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Submit_Button extends Button implements Clickable_Interface
{
    private bool $clicked = false;
    public function is_clicked(): bool
    {
        return $this->clicked;
    }
    /**
     * Submits data to the button.
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function submit(array|string|null $submitted_data, bool $clear_missing = true): static
    {
        if ($this->get_config()->get_disabled()) {
            $this->clicked = false;
            return $this;
        }
        parent::submit($submitted_data, $clear_missing);
        $this->clicked = null !== $submitted_data;
        return $this;
    }
}