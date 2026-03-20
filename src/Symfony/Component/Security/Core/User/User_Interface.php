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

namespace Symfony\Component\Security\Core\User;

/**
 * Represents the interface that all user classes must implement.
 *
 * This interface is useful because the authentication layer can deal with
 * the object through its lifecycle, assigning roles and so on.
 *
 * Regardless of how your users are loaded or where they come from (a database,
 * configuration, web service, etc.), you will have a class that implements
 * this interface. Objects that implement this interface are created and
 * loaded by different objects that implement UserProviderInterface.
 *
 * The __serialize/__unserialize() magic methods can be implemented on the user
 * class to prevent sensitive credentials from being put in the session storage.
 *
 * Security note: never store plain-text passwords on the UserInterface object
 * that reaches the session — only store the hashed password or omit it entirely.
 *
 * @see UserProviderInterface
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since 2.0
 */
interface UserInterface
{
    /**
     * Returns the roles granted to the user.
     *
     * Role names must follow the ROLE_ convention (e.g. ROLE_USER, ROLE_ADMIN).
     * At minimum, implementations should always return ['ROLE_USER'] so that
     * the voter system has a baseline role to work with.
     *
     *     public function getRoles(): array
     *     {
     *         return ['ROLE_USER'];
     *     }
     *
     * Alternatively, the roles might be stored in a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return string[] An array of role strings; must not be empty for authenticated users
     *
     * @since 2.0
     */
    public function getRoles(): array;

    /**
     * Returns the identifier for this user (e.g. username or email address).
     *
     * This value is used in token serialization, logs, and as the primary
     * user identifier across the security layer. It must be unique per user.
     *
     * @return non-empty-string A non-empty unique identifier for the user
     *
     * @since 5.3
     */
    public function getUserIdentifier(): string;
}
