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
use Symfony\Component\Form\Extension\Core\Type\Password_Type;
use Symfony\Component\Form\Extension\Password_Hasher\Event_Listener\Password_Hasher_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Property_Access\Property_Path;
/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class Password_Type_Password_Hasher_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly Password_Hasher_Listener $password_hasher_listener)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if ($options['hash_property_path']) {
            $builder->add_event_listener(Form_Events::POST_SUBMIT, $this->password_hasher_listener->register_password(...));
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['hash_property_path' => null]);
        $resolver->set_allowed_types('hash_property_path', ['null', 'string', Property_Path::class]);
        $resolver->set_info('hash_property_path', 'A valid PropertyAccess syntax where the hashed password will be set.');
    }
    public static function get_extended_types(): iterable
    {
        return [Password_Type::class];
    }
}