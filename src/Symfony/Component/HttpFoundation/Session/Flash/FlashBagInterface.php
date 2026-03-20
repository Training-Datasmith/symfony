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
namespace Symfony\Component\Http_Foundation\Session\Flash;

use Symfony\Component\Http_Foundation\Session\Session_Bag_Interface;
/**
 * FlashBagInterface.
 *
 * @author Drak <drak@zikula.org>
 */
interface Flash_Bag_Interface extends Session_Bag_Interface
{
    /**
     * Adds a flash message for the given type.
     */
    public function add(string $type, mixed $message): void;
    /**
     * Registers one or more messages for a given type.
     */
    public function set(string $type, string|array $messages): void;
    /**
     * Gets flash messages for a given type.
     *
     * @param string $type    Message category type
     * @param array  $default Default value if $type does not exist
     */
    public function peek(string $type, array $default = []): array;
    /**
     * Gets all flash messages.
     */
    public function peek_all(): array;
    /**
     * Gets and clears flash from the stack.
     *
     * @param array $default Default value if $type does not exist
     */
    public function get(string $type, array $default = []): array;
    /**
     * Gets and clears flashes from the stack.
     */
    public function all(): array;
    /**
     * Sets all flash messages.
     */
    public function set_all(array $messages): void;
    /**
     * Has flash messages for a given type?
     */
    public function has(string $type): bool;
    /**
     * Returns a list of all defined types.
     */
    public function keys(): array;
}