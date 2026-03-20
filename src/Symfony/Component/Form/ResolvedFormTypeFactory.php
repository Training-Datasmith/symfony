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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Resolved_Form_Type_Factory implements Resolved_Form_Type_Factory_Interface
{
    public function create_resolved_type(Form_Type_Interface $type, array $type_extensions, ?Resolved_Form_Type_Interface $parent = null): Resolved_Form_Type_Interface
    {
        return new Resolved_Form_Type($type, $type_extensions, $parent);
    }
}