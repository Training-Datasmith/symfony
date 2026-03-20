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
namespace Symfony\Component\Form\Extension\Password_Hasher\Event_Listener;

use Symfony\Component\Form\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Form\Extension\Core\Type\Repeated_Type;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Password_Hasher\Hasher\User_Password_Hasher_Interface;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Component\Security\Core\User\Password_Authenticated_User_Interface;
/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 * @author Gábor Egyed <gabor.egyed@gmail.com>
 */
class Password_Hasher_Listener
{
    private array $passwords = [];
    public function __construct(private readonly User_Password_Hasher_Interface $password_hasher, private ?Property_Accessor_Interface $property_accessor = null)
    {
        $this->property_accessor ??= Property_Access::create_property_accessor();
    }
    public function register_password(Form_Event $event): void
    {
        if (null === $event->get_data() || '' === $event->get_data()) {
            return;
        }
        $this->assert_not_mapped($event->get_form());
        $this->passwords[] = ['form' => $event->get_form(), 'property_path' => $event->get_form()->get_config()->get_option('hash_property_path'), 'password' => $event->get_data()];
    }
    public function hash_passwords(Form_Event $event): void
    {
        $form = $event->get_form();
        if (!$form->is_root()) {
            return;
        }
        if ($form->is_valid()) {
            foreach ($this->passwords as $password) {
                $user = $this->get_user($password['form']);
                $this->property_accessor->set_value($user, $password['property_path'], $this->password_hasher->hash_password($user, $password['password']));
            }
        }
        $this->passwords = [];
    }
    private function get_target_form(Form_Interface $form): Form_Interface
    {
        if (!$parent_form = $form->get_parent()) {
            return $form;
        }
        $parent_type = $parent_form->get_config()->get_type();
        do {
            if ($parent_type->get_inner_type() instanceof Repeated_Type) {
                return $parent_form;
            }
        } while ($parent_type = $parent_type->get_parent());
        return $form;
    }
    private function get_user(Form_Interface $form): Password_Authenticated_User_Interface
    {
        $parent = $this->get_target_form($form)->get_parent();
        if (!($user = $parent?->get_data()) || !$user instanceof Password_Authenticated_User_Interface) {
            throw new Invalid_Configuration_Exception(\sprintf('The "hash_property_path" option only supports "%s" objects, "%s" given.', Password_Authenticated_User_Interface::class, get_debug_type($user)));
        }
        return $user;
    }
    private function assert_not_mapped(Form_Interface $form): void
    {
        if ($this->get_target_form($form)->get_config()->get_mapped()) {
            throw new Invalid_Configuration_Exception('The "hash_property_path" option cannot be used on mapped field.');
        }
    }
}