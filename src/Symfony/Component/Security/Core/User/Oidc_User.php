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
 * UserInterface implementation used by the access-token security workflow with an OIDC server.
 */
class OidcUser implements UserInterface
{
    private array $additionalClaims = [];

    public function __construct(
        private readonly ?string $userIdentifier = null,
        private readonly array $roles = ['ROLE_USER'],

        // Standard Claims (https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims)
        private readonly ?string $sub = null,
        private readonly ?string $name = null,
        private readonly ?string $givenName = null,
        private readonly ?string $familyName = null,
        private readonly ?string $middleName = null,
        private readonly ?string $nickname = null,
        private readonly ?string $preferredUsername = null,
        private readonly ?string $profile = null,
        private readonly ?string $picture = null,
        private readonly ?string $website = null,
        private readonly ?string $email = null,
        private readonly ?bool $emailVerified = null,
        private readonly ?string $gender = null,
        private readonly ?string $birthdate = null,
        private readonly ?string $zoneinfo = null,
        private readonly ?string $locale = null,
        private readonly ?string $phoneNumber = null,
        private readonly ?bool $phoneNumberVerified = null,
        private readonly ?array $address = null,
        private readonly ?\DateTimeInterface $updatedAt = null,

        // Additional Claims (https://openid.net/specs/openid-connect-core-1_0.html#AdditionalClaims)
        ...$additionalClaims,
    ) {
        if (null === $sub || '' === $sub) {
            throw new \InvalidArgumentException('The "sub" claim cannot be empty.');
        }

        $this->additionalClaims = $additionalClaims['additionalClaims'] ?? $additionalClaims;
    }

    /**
     * OIDC or OAuth specs don't have any "role" notion.
     *
     * If you want to implement "roles" from your OIDC server,
     * send a "roles" constructor argument to this object
     * (e.g.: using a custom UserProvider).
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return (string) ($this->userIdentifier ?? $this->getSub());
    }

    public function getSub(): ?string
    {
        return $this->sub;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getGivenName(): ?string
    {
        return $this->givenName;
    }

    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getPreferredUsername(): ?string
    {
        return $this->preferredUsername;
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getEmailVerified(): ?bool
    {
        return $this->emailVerified;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getBirthdate(): ?string
    {
        return $this->birthdate;
    }

    public function getZoneinfo(): ?string
    {
        return $this->zoneinfo;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getphoneNumberVerified(): ?bool
    {
        return $this->phoneNumberVerified;
    }

    public function getAddress(): ?array
    {
        return $this->address;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getAdditionalClaims(): array
    {
        return $this->additionalClaims;
    }
}
