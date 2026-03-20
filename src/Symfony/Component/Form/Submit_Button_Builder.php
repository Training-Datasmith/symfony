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
 * A builder for {@link SubmitButton} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Submit_Button_Builder extends Button_Builder
{
    /**
     * Creates the button.
     */
    public function get_form(): Submit_Button
    {
        return new Submit_Button($this->get_form_config());
    }
}