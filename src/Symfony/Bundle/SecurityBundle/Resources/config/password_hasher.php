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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Core\Type\Password_Type;
use Symfony\Component\Form\Extension\Password_Hasher\Event_Listener\Password_Hasher_Listener;
use Symfony\Component\Form\Extension\Password_Hasher\Type\Form_Type_Password_Hasher_Extension;
use Symfony\Component\Form\Extension\Password_Hasher\Type\Password_Type_Password_Hasher_Extension;
use Symfony\Component\Password_Hasher\Hasher\Password_Hasher_Factory;
use Symfony\Component\Password_Hasher\Hasher\Password_Hasher_Factory_Interface;
use Symfony\Component\Password_Hasher\Hasher\User_Password_Hasher;
use Symfony\Component\Password_Hasher\Hasher\User_Password_Hasher_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.password_hasher_factory', Password_Hasher_Factory::class)->args([[]])->alias(Password_Hasher_Factory_Interface::class, 'security.password_hasher_factory')->set('security.user_password_hasher', User_Password_Hasher::class)->args([service('security.password_hasher_factory')])->alias('security.password_hasher', 'security.user_password_hasher')->alias(User_Password_Hasher_Interface::class, 'security.password_hasher')->set('form.listener.password_hasher', Password_Hasher_Listener::class)->args([service('security.password_hasher'), service('property_accessor')->null_on_invalid()])->set('form.type_extension.form.password_hasher', Form_Type_Password_Hasher_Extension::class)->args([service('form.listener.password_hasher')])->tag('form.type_extension', ['extended-type' => Form_Type::class])->set('form.type_extension.password.password_hasher', Password_Type_Password_Hasher_Extension::class)->args([service('form.listener.password_hasher')])->tag('form.type_extension', ['extended-type' => Password_Type::class]);
};