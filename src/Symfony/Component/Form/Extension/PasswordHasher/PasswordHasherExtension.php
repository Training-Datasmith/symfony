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
namespace Symfony\Component\Form\Extension\Password_Hasher;

use Symfony\Component\Form\Abstract_Extension;
use Symfony\Component\Form\Extension\Password_Hasher\Event_Listener\Password_Hasher_Listener;
/**
 * Integrates the PasswordHasher component with the Form library.
 *
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class Password_Hasher_Extension extends Abstract_Extension
{
    public function __construct(private readonly Password_Hasher_Listener $password_hasher_listener)
    {
    }
    protected function load_type_extensions(): array
    {
        return [new Type\Form_Type_Password_Hasher_Extension($this->password_hasher_listener), new Type\Password_Type_Password_Hasher_Extension($this->password_hasher_listener)];
    }
}