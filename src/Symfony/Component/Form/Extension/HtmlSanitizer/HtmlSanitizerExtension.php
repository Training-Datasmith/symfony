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
namespace Symfony\Component\Form\Extension\Html_Sanitizer;

use Psr\Container\Container_Interface;
use Symfony\Component\Form\Abstract_Extension;
/**
 * Integrates the HtmlSanitizer component with the Form library.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Html_Sanitizer_Extension extends Abstract_Extension
{
    public function __construct(private readonly Container_Interface $sanitizers, private readonly string $default_sanitizer = 'default')
    {
    }
    protected function load_type_extensions(): array
    {
        return [new Type\Text_Type_Html_Sanitizer_Extension($this->sanitizers, $this->default_sanitizer)];
    }
}