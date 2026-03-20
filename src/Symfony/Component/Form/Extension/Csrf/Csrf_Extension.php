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
namespace Symfony\Component\Form\Extension\Csrf;

use Symfony\Component\Form\Abstract_Extension;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * This extension protects forms by using a CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Csrf_Extension extends Abstract_Extension
{
    public function __construct(private readonly Csrf_Token_Manager_Interface $token_manager, private readonly ?Translator_Interface $translator = null, private readonly ?string $translation_domain = null)
    {
    }
    protected function load_type_extensions(): array
    {
        return [new Type\Form_Type_Csrf_Extension($this->token_manager, true, '_token', $this->translator, $this->translation_domain)];
    }
}