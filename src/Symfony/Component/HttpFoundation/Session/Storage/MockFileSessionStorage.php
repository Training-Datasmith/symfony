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

/**
 * MockFileSessionStorage is used to mock sessions for
 * functional testing where you may need to persist session data
 * across separate PHP processes.
 *
 * No PHP session is actually started since a session can be initialized
 * and shutdown only once per PHP execution cycle and this class does
 * not pollute any session related globals, including session_*() functions
 * or session.* PHP ini directives.
 *
 * @author Drak <drak@zikula.org>
 */
class Mock_File_Session_Storage extends Mock_Array_Session_Storage
{
    private readonly string $save_path;
    /**
     * @param string|null $savePath Path of directory to save session files
     */
    public function __construct(?string $save_path = null, string $name = 'MOCKSESSID', ?Metadata_Bag $meta_bag = null)
    {
        $save_path ??= sys_get_temp_dir();
        if (!is_dir($save_path) && !@mkdir($save_path, 0777, true) && !is_dir($save_path)) {
            throw new \RuntimeException(\sprintf('Session Storage was not able to create directory "%s".', $save_path));
        }
        $this->save_path = $save_path;
        parent::__construct($name, $meta_bag);
    }
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }
        if (!$this->id) {
            $this->id = $this->generate_id();
        }
        $this->read();
        $this->started = true;
        return true;
    }
    public function regenerate(bool $destroy = false, ?int $lifetime = null): bool
    {
        if (!$this->started) {
            $this->start();
        }
        if ($destroy) {
            $this->destroy();
        }
        return parent::regenerate($destroy, $lifetime);
    }
    public function save(): void
    {
        if (!$this->started) {
            throw new \RuntimeException('Trying to save a session that was not started yet or was already closed.');
        }
        $data = $this->data;
        foreach ($this->bags as $bag) {
            if (empty($data[$key = $bag->get_storage_key()])) {
                unset($data[$key]);
            }
        }
        if ([$key = $this->metadata_bag->get_storage_key()] === array_keys($data)) {
            unset($data[$key]);
        }
        try {
            if ($data) {
                $path = $this->get_file_path();
                $tmp = $path . bin2hex(random_bytes(6));
                file_put_contents($tmp, serialize($data));
                rename($tmp, $path);
            } else {
                $this->destroy();
            }
        } finally {
            $this->data = $data;
        }
        // this is needed when the session object is reused across multiple requests
        // in functional tests.
        $this->started = false;
    }
    /**
     * Deletes a session from persistent storage.
     * Deliberately leaves session data in memory intact.
     */
    private function destroy(): void
    {
        set_error_handler(static function (): void {
        });
        try {
            unlink($this->get_file_path());
        } finally {
            restore_error_handler();
        }
    }
    /**
     * Calculate path to file.
     */
    private function get_file_path(): string
    {
        return $this->save_path . '/' . $this->id . '.mocksess';
    }
    /**
     * Reads session from storage and loads session.
     */
    private function read(): void
    {
        set_error_handler(static function (): void {
        });
        try {
            $data = file_get_contents($this->get_file_path());
        } finally {
            restore_error_handler();
        }
        $this->data = $data ? unserialize($data) : [];
        $this->load_session();
    }
}