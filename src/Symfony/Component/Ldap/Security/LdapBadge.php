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

namespace Symfony\Component\Ldap\Security;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * A badge indicating that the credentials should be checked using LDAP.
 *
 * This badge must be used together with PasswordCredentials.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class LdapBadge implements BadgeInterface
{
    private bool $resolved = false;
    private readonly string $queryString;

    public function __construct(
        private readonly string $ldapServiceId,
        private readonly string $dnString = '{user_identifier}',
        private readonly string $searchDn = '',
        private readonly string $searchPassword = '',
        ?string $queryString = null,
    ) {
        $this->queryString = $queryString ?? '';
    }

    public function getLdapServiceId(): string
    {
        return $this->ldapServiceId;
    }

    public function getDnString(): string
    {
        return $this->dnString;
    }

    public function getSearchDn(): string
    {
        return $this->searchDn;
    }

    public function getSearchPassword(): string
    {
        return $this->searchPassword;
    }

    public function getQueryString(): string
    {
        return $this->queryString;
    }

    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
