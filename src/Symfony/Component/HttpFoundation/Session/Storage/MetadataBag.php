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
namespace Symfony\Component\Http_Foundation\Session\Storage;

use Symfony\Component\Http_Foundation\Session\Session_Bag_Interface;
/**
 * Metadata container.
 *
 * Adds metadata to the session.
 *
 * @author Drak <drak@zikula.org>
 */
class Metadata_Bag implements Session_Bag_Interface
{
    public const CREATED = 'c';
    public const UPDATED = 'u';
    public const LIFETIME = 'l';
    protected array $meta = [self::CREATED => 0, self::UPDATED => 0, self::LIFETIME => 0];
    private string $name = '__metadata';
    private int $last_used;
    /**
     * @param string $storageKey      The key used to store bag in the session
     * @param int    $updateThreshold The time to wait between two UPDATED updates
     */
    public function __construct(private readonly string $storage_key = '_sf2_meta', private readonly int $update_threshold = 0)
    {
    }
    public function initialize(array &$array): void
    {
        $this->meta =& $array;
        if (isset($array[self::CREATED])) {
            $this->last_used = $this->meta[self::UPDATED];
            $time_stamp = time();
            if ($time_stamp - $array[self::UPDATED] >= $this->update_threshold) {
                $this->meta[self::UPDATED] = $time_stamp;
            }
        } else {
            $this->stamp_created();
        }
    }
    /**
     * Gets the lifetime that the session cookie was set with.
     */
    public function get_lifetime(): int
    {
        return $this->meta[self::LIFETIME];
    }
    /**
     * Stamps a new session's metadata.
     *
     * @param int|null $lifetime Sets the cookie lifetime for the session cookie. A null value
     *                           will leave the system settings unchanged, 0 sets the cookie
     *                           to expire with browser session. Time is in seconds, and is
     *                           not a Unix timestamp.
     */
    public function stamp_new(?int $lifetime = null): void
    {
        $this->stamp_created($lifetime);
    }
    public function get_storage_key(): string
    {
        return $this->storage_key;
    }
    /**
     * Gets the created timestamp metadata.
     *
     * @return int Unix timestamp
     */
    public function get_created(): int
    {
        return $this->meta[self::CREATED];
    }
    /**
     * Gets the last used metadata.
     *
     * @return int Unix timestamp
     */
    public function get_last_used(): int
    {
        return $this->last_used;
    }
    public function clear(): mixed
    {
        // nothing to do
        return null;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Sets name.
     */
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    private function stamp_created(?int $lifetime = null): void
    {
        $time_stamp = time();
        $this->meta[self::CREATED] = $this->meta[self::UPDATED] = $this->last_used = $time_stamp;
        $this->meta[self::LIFETIME] = $lifetime ?? (int) \ini_get('session.cookie_lifetime');
    }
}