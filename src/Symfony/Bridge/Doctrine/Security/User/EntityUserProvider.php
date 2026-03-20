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
namespace Symfony\Bridge\Doctrine\Security\User;

use Doctrine\Persistence\Manager_Registry;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Doctrine\Persistence\Object_Repository;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Security\Core\Exception\Unsupported_User_Exception;
use Symfony\Component\Security\Core\Exception\User_Not_Found_Exception;
use Symfony\Component\Security\Core\User\Attributes_Based_User_Provider_Interface;
use Symfony\Component\Security\Core\User\Password_Authenticated_User_Interface;
use Symfony\Component\Security\Core\User\Password_Upgrader_Interface;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Core\User\User_Provider_Interface;
/**
 * Wrapper around a Doctrine ObjectManager.
 *
 * Provides provisioning for Doctrine entity users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @template TUser of UserInterface
 *
 * @template-implements AttributesBasedUserProviderInterface<TUser>
 */
class Entity_User_Provider implements Attributes_Based_User_Provider_Interface, Password_Upgrader_Interface
{
    private string $class;
    public function __construct(private readonly Manager_Registry $registry, private readonly string $class_or_alias, private readonly ?string $property = null, private readonly ?string $manager_name = null)
    {
    }
    public function load_user_by_identifier(string $identifier, ?array $attributes = null): User_Interface
    {
        $repository = $this->get_repository();
        if (null !== $this->property) {
            $user = $repository->find_one_by([$this->property => $identifier]);
        } else {
            if (!$repository instanceof User_Loader_Interface) {
                throw new \InvalidArgumentException(\sprintf('You must either make the "%s" entity Doctrine Repository ("%s") implement "Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface" or set the "property" option in the corresponding entity provider configuration.', $this->class_or_alias, get_debug_type($repository)));
            }
            if (null === $attributes) {
                $user = $repository->load_user_by_identifier($identifier);
            } else {
                $user = $repository->load_user_by_identifier($identifier, $attributes);
            }
        }
        if (null === $user) {
            $e = new User_Not_Found_Exception(\sprintf('User "%s" not found.', $identifier));
            $e->set_user_identifier($identifier);
            throw $e;
        }
        return $user;
    }
    public function refresh_user(User_Interface $user): User_Interface
    {
        $class = $this->get_class();
        if (!$user instanceof $class) {
            throw new Unsupported_User_Exception(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }
        $repository = $this->get_repository();
        if ($repository instanceof User_Provider_Interface) {
            $refreshed_user = $repository->refresh_user($user);
        } else {
            // The user must be reloaded via the primary key as all other data
            // might have changed without proper persistence in the database.
            // That's the case when the user has been changed by a form with
            // validation errors.
            if (!$id = $this->get_class_metadata()->get_identifier_values($user)) {
                throw new \InvalidArgumentException('You cannot refresh a user from the EntityUserProvider that does not contain an identifier. The user object has to be serialized with its own identifier mapped by Doctrine.');
            }
            $refreshed_user = $repository->find($id);
            if (null === $refreshed_user) {
                $e = new User_Not_Found_Exception('User with id ' . json_encode($id) . ' not found.');
                $e->set_user_identifier(json_encode($id));
                throw $e;
            }
        }
        if ($refreshed_user instanceof Proxy && !$refreshed_user->__is_initialized()) {
            $refreshed_user->__load();
        } elseif (($r = new \ReflectionClass($refreshed_user))->is_uninitialized_lazy_object($refreshed_user)) {
            $r->initialize_lazy_object($refreshed_user);
        }
        return $refreshed_user;
    }
    public function supports_class(string $class): bool
    {
        if ($class === $this->get_class()) {
            return true;
        }
        return is_subclass_of($class, $this->get_class());
    }
    /**
     * @final
     */
    public function upgrade_password(Password_Authenticated_User_Interface $user, string $new_hashed_password): void
    {
        $class = $this->get_class();
        if (!$user instanceof $class) {
            throw new Unsupported_User_Exception(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }
        $repository = $this->get_repository();
        if ($repository instanceof Password_Upgrader_Interface) {
            $repository->upgrade_password($user, $new_hashed_password);
        }
    }
    private function get_object_manager(): Object_Manager
    {
        return $this->registry->get_manager($this->manager_name);
    }
    private function get_repository(): Object_Repository
    {
        return $this->get_object_manager()->get_repository($this->class_or_alias);
    }
    private function get_class(): string
    {
        if (!isset($this->class)) {
            $class = $this->class_or_alias;
            if (str_contains($class, ':')) {
                $class = $this->get_class_metadata()->get_name();
            }
            $this->class = $class;
        }
        return $this->class;
    }
    private function get_class_metadata(): Class_Metadata
    {
        return $this->get_object_manager()->get_class_metadata($this->class_or_alias);
    }
}