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
namespace Symfony\Component\Http_Foundation\Session;

/**
 * Session Bag store.
 *
 * @author Drak <drak@zikula.org>
 */
interface Session_Bag_Interface
{
    /**
     * Gets this bag's name.
     */
    public function get_name(): string;
    /**
     * Initializes the Bag.
     */
    public function initialize(array &$array): void;
    /**
     * Gets the storage key for this bag.
     */
    public function get_storage_key(): string;
    /**
     * Clears out data from bag.
     *
     * @return mixed Whatever data was contained
     */
    public function clear(): mixed;
}