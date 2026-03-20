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
namespace Symfony\Bridge\Twig;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Session\Flash_Bag_Aware_Session_Interface;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Token_Interface;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Translation\Locale_Switcher;
/**
 * Exposes some Symfony parameters and services as an "app" global variable.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class App_Variable
{
    private Token_Storage_Interface $token_storage;
    private Request_Stack $request_stack;
    private string $environment;
    private bool $debug;
    private Locale_Switcher $locale_switcher;
    private array $enabled_locales;
    public function set_token_storage(Token_Storage_Interface $token_storage): void
    {
        $this->token_storage = $token_storage;
    }
    public function set_request_stack(Request_Stack $request_stack): void
    {
        $this->request_stack = $request_stack;
    }
    public function set_environment(string $environment): void
    {
        $this->environment = $environment;
    }
    public function set_debug(bool $debug): void
    {
        $this->debug = $debug;
    }
    public function set_locale_switcher(Locale_Switcher $locale_switcher): void
    {
        $this->locale_switcher = $locale_switcher;
    }
    public function set_enabled_locales(array $enabled_locales): void
    {
        $this->enabled_locales = array_filter($enabled_locales);
    }
    /**
     * Returns the current token.
     *
     * @throws \RuntimeException When the TokenStorage is not available
     */
    public function get_token(): ?Token_Interface
    {
        if (!isset($this->token_storage)) {
            throw new \RuntimeException('The "app.token" variable is not available.');
        }
        return $this->token_storage->get_token();
    }
    /**
     * Returns the current user.
     *
     * @see TokenInterface::getUser()
     */
    public function get_user(): ?User_Interface
    {
        if (!isset($this->token_storage)) {
            throw new \RuntimeException('The "app.user" variable is not available.');
        }
        return $this->token_storage->get_token()?->get_user();
    }
    /**
     * Returns the current request.
     */
    public function get_request(): ?Request
    {
        if (!isset($this->request_stack)) {
            throw new \RuntimeException('The "app.request" variable is not available.');
        }
        return $this->request_stack->get_current_request();
    }
    /**
     * Returns the current session.
     */
    public function get_session(): ?Session_Interface
    {
        if (!isset($this->request_stack)) {
            throw new \RuntimeException('The "app.session" variable is not available.');
        }
        $request = $this->get_request();
        return $request?->has_session() ? $request->get_session() : null;
    }
    /**
     * Returns the current app environment.
     */
    public function get_environment(): string
    {
        if (!isset($this->environment)) {
            throw new \RuntimeException('The "app.environment" variable is not available.');
        }
        return $this->environment;
    }
    /**
     * Returns the current app debug mode.
     */
    public function get_debug(): bool
    {
        if (!isset($this->debug)) {
            throw new \RuntimeException('The "app.debug" variable is not available.');
        }
        return $this->debug;
    }
    public function get_locale(): string
    {
        if (!isset($this->locale_switcher)) {
            throw new \RuntimeException('The "app.locale" variable is not available.');
        }
        return $this->locale_switcher->get_locale();
    }
    public function get_enabled_locales(): array
    {
        if (!isset($this->enabled_locales)) {
            throw new \RuntimeException('The "app.enabled_locales" variable is not available.');
        }
        return $this->enabled_locales;
    }
    /**
     * Returns some or all the existing flash messages:
     *  * getFlashes() returns all the flash messages
     *  * getFlashes('notice') returns a simple array with flash messages of that type
     *  * getFlashes(['notice', 'error']) returns a nested array of type => messages.
     */
    public function get_flashes(string|array|null $types = null): array
    {
        try {
            $session = $this->get_session();
        } catch (\RuntimeException) {
            return [];
        }
        if (!$session instanceof Flash_Bag_Aware_Session_Interface) {
            return [];
        }
        if (null === $types || '' === $types || [] === $types) {
            return $session->get_flash_bag()->all();
        }
        if (\is_string($types)) {
            return $session->get_flash_bag()->get($types);
        }
        $result = [];
        foreach ($types as $type) {
            $result[$type] = $session->get_flash_bag()->get($type);
        }
        return $result;
    }
    public function get_current_route(): ?string
    {
        if (!isset($this->request_stack)) {
            throw new \RuntimeException('The "app.current_route" variable is not available.');
        }
        return $this->get_request()?->attributes->get('_route');
    }
    /**
     * @return array<string, mixed>
     */
    public function get_current_route_parameters(): array
    {
        if (!isset($this->request_stack)) {
            throw new \RuntimeException('The "app.current_route_parameters" variable is not available.');
        }
        return $this->get_request()?->attributes->get('_route_params') ?? [];
    }
}