<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * TokenInterface is the interface for the user authentication information.
 *
 * A token represents the authentication state of a user within a firewall context.
 * It carries the authenticated UserInterface object, the user's roles, and any
 * custom attributes set by security voters or listeners.
 *
 * The __serialize/__unserialize() magic methods can be implemented on the token
 * class to prevent sensitive credentials (e.g. passwords) from being put in the
 * session storage. Only the minimum necessary data should be serialized.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since 2.0
 */
interface TokenInterface extends \Stringable
{
    /**
     * Returns a string representation of the Token.
     *
     * This is only to be used for debugging purposes.
     */
    public function __toString(): string;

    /**
     * Returns the user identifier used during authentication (e.g. a user's email address or username).
     */
    public function getUserIdentifier(): string;

    /**
     * Returns the user roles.
     *
     * @return string[]
     */
    public function getRoleNames(): array;

    /**
     * Returns a user representation.
     *
     * @see AbstractToken::setUser()
     */
    public function getUser(): ?UserInterface;

    /**
     * Sets the authenticated user in the token.
     *
     * @throws \InvalidArgumentException
     */
    public function setUser(UserInterface $user): void;

    /**
     * Returns all custom attributes associated with the token.
     *
     * Attributes are arbitrary key-value pairs set by security voters or listeners
     * to pass data between security components without touching the user object.
     *
     * @return array<string, mixed> All token attributes
     *
     * @since 2.0
     */
    public function getAttributes(): array;

    /**
     * Replaces all token attributes at once.
     *
     * @param array<string, mixed> $attributes The new token attributes; replaces existing ones entirely
     *
     * @since 2.0
     */
    public function setAttributes(array $attributes): void;

    /**
     * Returns whether a named attribute exists on the token.
     *
     * @param string $name The attribute name to check
     *
     * @return bool True if the attribute exists (even if its value is null)
     *
     * @since 2.0
     */
    public function hasAttribute(string $name): bool;

    /**
     * Returns the value of a named attribute.
     *
     * @param string $name The attribute name
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When the attribute does not exist for this token
     *
     * @since 2.0
     */
    public function getAttribute(string $name): mixed;

    /**
     * Sets a named attribute on the token.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value; should be serializable for session storage
     *
     * @since 2.0
     */
    public function setAttribute(string $name, mixed $value): void;

    /**
     * Returns all the necessary state of the object for serialization purposes.
     */
    public function __serialize(): array;

    /**
     * Restores the object state from an array given by __serialize().
     */
    public function __unserialize(array $data): void;
}
