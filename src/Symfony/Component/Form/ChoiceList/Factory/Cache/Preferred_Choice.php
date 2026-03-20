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
namespace Symfony\Component\Form\Choice_List\Factory\Cache;

use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Component\Form\Form_Type_Interface;
/**
 * A cacheable wrapper for any {@see FormTypeInterface} or {@see FormTypeExtensionInterface}
 * which configures a "preferred_choices" option.
 *
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Preferred_Choice extends Abstract_Static_Option
{
}