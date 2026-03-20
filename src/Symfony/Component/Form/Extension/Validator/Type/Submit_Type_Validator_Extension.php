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
namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\Extension\Core\Type\Submit_Type;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Submit_Type_Validator_Extension extends Base_Validator_Extension
{
    public static function get_extended_types(): iterable
    {
        return [Submit_Type::class];
    }
}