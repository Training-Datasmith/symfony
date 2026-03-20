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
namespace Symfony\Component\Form\Extension\Password_Hasher\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Password_Hasher\Event_Listener\Password_Hasher_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Events;
/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class Form_Type_Password_Hasher_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly Password_Hasher_Listener $password_hasher_listener)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_event_listener(Form_Events::POST_SUBMIT, $this->password_hasher_listener->hash_passwords(...));
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}