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
namespace Symfony\Component\Form\Extension\Http_Foundation;

use Symfony\Component\Form\Abstract_Extension;
/**
 * Integrates the HttpFoundation component with the Form library.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Http_Foundation_Extension extends Abstract_Extension
{
    protected function load_type_extensions(): array
    {
        return [new Type\Form_Type_Http_Foundation_Extension(), new Type\Form_Flow_Type_Session_Data_Storage_Extension()];
    }
}