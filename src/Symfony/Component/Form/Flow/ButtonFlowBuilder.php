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
namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Button_Builder;
/**
 * A builder for {@link ButtonFlow} instances.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Button_Flow_Builder extends Button_Builder
{
    public function get_form(): Button_Flow
    {
        return new Button_Flow($this->get_form_config());
    }
}