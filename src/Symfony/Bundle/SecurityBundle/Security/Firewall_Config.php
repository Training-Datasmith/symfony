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
namespace Symfony\Bundle\Security_Bundle\Security;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final readonly class Firewall_Config
{
    public function __construct(private string $name, private string $user_checker, private ?string $request_matcher = null, private bool $security_enabled = true, private bool $stateless = false, private ?string $provider = null, private ?string $context = null, private ?string $entry_point = null, private ?string $access_denied_handler = null, private ?string $access_denied_url = null, private array $authenticators = [], private ?array $switch_user = null, private ?array $logout = null)
    {
    }
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * @return string|null The request matcher service id or null if neither the request matcher, pattern or host
     *                     options were provided
     */
    public function get_request_matcher(): ?string
    {
        return $this->request_matcher;
    }
    public function is_security_enabled(): bool
    {
        return $this->security_enabled;
    }
    public function is_stateless(): bool
    {
        return $this->stateless;
    }
    public function get_provider(): ?string
    {
        return $this->provider;
    }
    /**
     * @return string|null The context key (will be null if the firewall is stateless)
     */
    public function get_context(): ?string
    {
        return $this->context;
    }
    public function get_entry_point(): ?string
    {
        return $this->entry_point;
    }
    public function get_user_checker(): string
    {
        return $this->user_checker;
    }
    public function get_access_denied_handler(): ?string
    {
        return $this->access_denied_handler;
    }
    public function get_access_denied_url(): ?string
    {
        return $this->access_denied_url;
    }
    public function get_authenticators(): array
    {
        return $this->authenticators;
    }
    public function get_switch_user(): ?array
    {
        return $this->switch_user;
    }
    public function get_logout(): ?array
    {
        return $this->logout;
    }
}