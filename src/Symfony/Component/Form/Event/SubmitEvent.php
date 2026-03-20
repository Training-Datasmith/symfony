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
namespace Symfony\Component\Form\Event;

use Symfony\Component\Form\Form_Event;
/**
 * This event is dispatched just before the Form::submit() method
 * transforms back the normalized data to the model and view data.
 *
 * It can be used to change data from the normalized representation of the data.
 */
final class Submit_Event extends Form_Event
{
}