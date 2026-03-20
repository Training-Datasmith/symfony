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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Security\Acl\Voter\Field_Vote;
use Symfony\Component\Security\Core\Authorization\Access_Decision;
use Symfony\Component\Security\Core\Authorization\Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Authorization\User_Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Exception\Authentication_Credentials_Not_Found_Exception;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Http\Impersonate\Impersonate_Url_Generator;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * SecurityExtension exposes security context features.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Security_Extension extends Abstract_Extension
{
    public function __construct(private readonly ?Authorization_Checker_Interface $security_checker = null, private readonly ?Impersonate_Url_Generator $impersonate_url_generator = null)
    {
    }
    public function is_granted(mixed $role, mixed $object = null, ?string $field = null, ?Access_Decision $access_decision = null): bool
    {
        if (null === $this->security_checker) {
            return false;
        }
        if (null !== $field) {
            if (!class_exists(Field_Vote::class)) {
                throw new \LogicException('Passing a $field to the "is_granted()" function requires symfony/acl. Try running "composer require symfony/acl-bundle" if you need field-level access control.');
            }
            $object = new Field_Vote($object, $field);
        }
        try {
            return $this->security_checker->is_granted($role, $object, $access_decision);
        } catch (Authentication_Credentials_Not_Found_Exception) {
            return false;
        }
    }
    public function get_access_decision(mixed $role, mixed $object = null, ?string $field = null): Access_Decision
    {
        if (!class_exists(Access_Decision::class)) {
            throw new \LogicException(\sprintf('Using the "access_decision()" function requires symfony/security-core >= 7.3. Try running "composer %s symfony/security-core".', $this->security_checker ? 'update' : 'require'));
        }
        $access_decision = new Access_Decision();
        $this->is_granted($role, $object, $field, $access_decision);
        return $access_decision;
    }
    public function is_granted_for_user(User_Interface $user, mixed $attribute, mixed $subject = null, ?string $field = null, ?Access_Decision $access_decision = null): bool
    {
        if (null === $this->security_checker) {
            return false;
        }
        if (!$this->security_checker instanceof User_Authorization_Checker_Interface) {
            throw new \LogicException(\sprintf('You cannot use "%s()" if the authorization checker doesn\'t implement "%s".', __METHOD__, User_Authorization_Checker_Interface::class));
        }
        if (null !== $field) {
            if (!class_exists(Field_Vote::class)) {
                throw new \LogicException('Passing a $field to the "is_granted_for_user()" function requires symfony/acl. Try running "composer require symfony/acl-bundle" if you need field-level access control.');
            }
            $subject = new Field_Vote($subject, $field);
        }
        try {
            return $this->security_checker->is_granted_for_user($user, $attribute, $subject, $access_decision);
        } catch (Authentication_Credentials_Not_Found_Exception) {
            return false;
        }
    }
    public function get_access_decision_for_user(User_Interface $user, mixed $attribute, mixed $subject = null, ?string $field = null): Access_Decision
    {
        if (!class_exists(Access_Decision::class)) {
            throw new \LogicException(\sprintf('Using the "access_decision_for_user()" function requires symfony/security-core >= 7.3. Try running "composer %s symfony/security-core".', $this->security_checker ? 'update' : 'require'));
        }
        $access_decision = new Access_Decision();
        $this->is_granted_for_user($user, $attribute, $subject, $field, $access_decision);
        return $access_decision;
    }
    public function get_impersonate_exit_url(?string $exit_to = null): string
    {
        if (null === $this->impersonate_url_generator) {
            return '';
        }
        return $this->impersonate_url_generator->generate_exit_url($exit_to);
    }
    public function get_impersonate_exit_path(?string $exit_to = null): string
    {
        if (null === $this->impersonate_url_generator) {
            return '';
        }
        return $this->impersonate_url_generator->generate_exit_path($exit_to);
    }
    public function get_impersonate_url(string $identifier): string
    {
        if (null === $this->impersonate_url_generator) {
            return '';
        }
        return $this->impersonate_url_generator->generate_impersonation_url($identifier);
    }
    public function get_impersonate_path(string $identifier): string
    {
        if (null === $this->impersonate_url_generator) {
            return '';
        }
        return $this->impersonate_url_generator->generate_impersonation_path($identifier);
    }
    public function get_functions(): array
    {
        $functions = [new Twig_Function('is_granted', $this->is_granted(...)), new Twig_Function('access_decision', $this->get_access_decision(...)), new Twig_Function('impersonation_exit_url', $this->get_impersonate_exit_url(...)), new Twig_Function('impersonation_exit_path', $this->get_impersonate_exit_path(...)), new Twig_Function('impersonation_url', $this->get_impersonate_url(...)), new Twig_Function('impersonation_path', $this->get_impersonate_path(...))];
        if ($this->security_checker instanceof User_Authorization_Checker_Interface) {
            $functions[] = new Twig_Function('is_granted_for_user', $this->is_granted_for_user(...));
            $functions[] = new Twig_Function('access_decision_for_user', $this->get_access_decision_for_user(...));
        }
        return $functions;
    }
}