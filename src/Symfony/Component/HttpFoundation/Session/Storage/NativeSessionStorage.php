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
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Strict_Session_Handler;
use Symfony\Component\Http_Foundation\Session\Storage\Proxy\Abstract_Proxy;
use Symfony\Component\Http_Foundation\Session\Storage\Proxy\Session_Handler_Proxy;
// Help opcache.preload discover always-needed symbols
class_exists(Metadata_Bag::class);
class_exists(Strict_Session_Handler::class);
class_exists(Session_Handler_Proxy::class);
/**
 * This provides a base class for session attribute storage.
 *
 * @author Drak <drak@zikula.org>
 */
class Native_Session_Storage implements Session_Storage_Interface
{
    /**
     * @var SessionBagInterface[]
     */
    protected array $bags = [];
    protected bool $started = false;
    protected bool $closed = false;
    protected Abstract_Proxy|\Session_Handler_Interface $save_handler;
    protected Metadata_Bag $metadata_bag;
    /**
     * Depending on how you want the storage driver to behave you probably
     * want to override this constructor entirely.
     *
     * List of options for $options array with their defaults.
     *
     * @see https://php.net/session.configuration for options
     * but we omit 'session.' from the beginning of the keys for convenience.
     *
     * ("auto_start", is not supported as it tells PHP to start a session before
     * PHP starts to execute user-land code. Setting during runtime has no effect).
     *
     * cache_limiter, "" (use "0" to prevent headers from being sent entirely).
     * cache_expire, "0"
     * cookie_domain, ""
     * cookie_httponly, ""
     * cookie_lifetime, "0"
     * cookie_path, "/"
     * cookie_secure, ""
     * cookie_samesite, null
     * gc_divisor, "100"
     * gc_maxlifetime, "1440"
     * gc_probability, "1"
     * lazy_write, "1"
     * name, "PHPSESSID"
     * serialize_handler, "php"
     * use_strict_mode, "1"
     * use_cookies, "1"
     */
    public function __construct(array $options = [], Abstract_Proxy|\Session_Handler_Interface|null $handler = null, ?Metadata_Bag $meta_bag = null)
    {
        if (!\extension_loaded('session')) {
            throw new \LogicException('PHP extension "session" is required.');
        }
        $options += ['cache_limiter' => '', 'cache_expire' => 0, 'use_cookies' => 1, 'lazy_write' => 1, 'use_strict_mode' => 1];
        session_register_shutdown();
        $this->set_metadata_bag($meta_bag);
        $this->set_options($options);
        $this->set_save_handler($handler);
    }
    /**
     * Gets the save handler instance.
     */
    public function get_save_handler(): Abstract_Proxy|\Session_Handler_Interface
    {
        return $this->save_handler;
    }
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }
        if (\PHP_SESSION_ACTIVE === session_status()) {
            throw new \RuntimeException('Failed to start the session: already started by PHP.');
        }
        if (filter_var(\ini_get('session.use_cookies'), \FILTER_VALIDATE_BOOL) && headers_sent($file, $line)) {
            throw new \RuntimeException(\sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
        }
        $session_id = $_COOKIE[session_name()] ?? null;
        /*
         * Explanation of the session ID regular expression: `/^[a-zA-Z0-9,-]{22,250}$/`.
         *
         * ---------- Part 1
         *
         * The part `[a-zA-Z0-9,-]` corresponds to the character range when PHP's `session.sid_bits_per_character` is set to 6.
         * See https://php.net/session.configuration#ini.session.sid-bits-per-character
         *
         * ---------- Part 2
         *
         * The part `{22,250}` defines the acceptable length range for session IDs.
         * See https://php.net/session.configuration#ini.session.sid-length
         * Allowed values are integers between 22 and 256, but we use 250 for the max.
         *
         * Where does the 250 come from?
         * - The length of Windows and Linux filenames is limited to 255 bytes. Then the max must not exceed 255.
         * - The session filename prefix is `sess_`, a 5 bytes string. Then the max must not exceed 255 - 5 = 250.
         *
         * ---------- Conclusion
         *
         * The parts 1 and 2 prevent the warning below:
         * `PHP Warning: SessionHandler::read(): Session ID is too long or contains illegal characters. Only the A-Z, a-z, 0-9, "-", and "," characters are allowed.`
         *
         * The part 2 prevents the warning below:
         * `PHP Warning: SessionHandler::read(): open(filepath, O_RDWR) failed: No such file or directory (2).`
         */
        if ($session_id && $this->save_handler instanceof Abstract_Proxy && 'files' === $this->save_handler->get_save_handler_name() && !preg_match('/^[a-zA-Z0-9,-]{22,250}$/', (string) $session_id)) {
            // the session ID in the header is invalid, create a new one
            session_id(session_create_id());
        }
        // ok to try and start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start the session.');
        }
        $this->load_session();
        return true;
    }
    public function get_id(): string
    {
        return $this->save_handler->get_id();
    }
    public function set_id(string $id): void
    {
        $this->save_handler->set_id($id);
    }
    public function get_name(): string
    {
        return $this->save_handler->get_name();
    }
    public function set_name(string $name): void
    {
        $this->save_handler->set_name($name);
    }
    public function regenerate(bool $destroy = false, ?int $lifetime = null): bool
    {
        // Cannot regenerate the session ID for non-active sessions.
        if (\PHP_SESSION_ACTIVE !== session_status()) {
            return false;
        }
        if (headers_sent()) {
            return false;
        }
        if (null !== $lifetime && $lifetime != \ini_get('session.cookie_lifetime')) {
            $this->save();
            ini_set('session.cookie_lifetime', $lifetime);
            $this->start();
        }
        if ($destroy) {
            $this->metadata_bag->stamp_new();
        }
        return session_regenerate_id($destroy);
    }
    public function save(): void
    {
        // Store a copy so we can restore the bags in case the session was not left empty
        $session = $_SESSION;
        foreach ($this->bags as $bag) {
            if (empty($_SESSION[$key = $bag->get_storage_key()])) {
                unset($_SESSION[$key]);
            }
        }
        if ($_SESSION && [$key = $this->metadata_bag->get_storage_key()] === array_keys($_SESSION)) {
            unset($_SESSION[$key]);
        }
        // Register error handler to add information about the current save handler
        $previous_handler = set_error_handler(function ($type, $msg, $file, $line) use (&$previous_handler) {
            if (\E_WARNING === $type && str_starts_with($msg, 'session_write_close():')) {
                $handler = $this->save_handler instanceof Session_Handler_Proxy ? $this->save_handler->get_handler() : $this->save_handler;
                $msg = \sprintf('session_write_close(): Failed to write session data with "%s" handler', $handler::class);
            }
            return $previous_handler ? $previous_handler($type, $msg, $file, $line) : false;
        });
        try {
            session_write_close();
        } finally {
            restore_error_handler();
            // Restore only if not empty
            if ($_SESSION) {
                $_SESSION = $session;
            }
        }
        $this->closed = true;
        $this->started = false;
    }
    public function clear(): void
    {
        // clear out the bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }
        // clear out the session
        $_SESSION = [];
        // reconnect the bags to the session
        $this->load_session();
    }
    public function register_bag(Session_Bag_Interface $bag): void
    {
        if ($this->started) {
            throw new \LogicException('Cannot register a bag when the session is already started.');
        }
        $this->bags[$bag->get_name()] = $bag;
    }
    public function get_bag(string $name): Session_Bag_Interface
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(\sprintf('The SessionBagInterface "%s" is not registered.', $name));
        }
        if (!$this->started && $this->save_handler->is_active()) {
            $this->load_session();
        } elseif (!$this->started) {
            $this->start();
        }
        return $this->bags[$name];
    }
    public function set_metadata_bag(?Metadata_Bag $meta_bag): void
    {
        $this->metadata_bag = $meta_bag ?? new Metadata_Bag();
    }
    /**
     * Gets the MetadataBag.
     */
    public function get_metadata_bag(): Metadata_Bag
    {
        return $this->metadata_bag;
    }
    public function is_started(): bool
    {
        return $this->started;
    }
    /**
     * Sets session.* ini variables.
     *
     * For convenience we omit 'session.' from the beginning of the keys.
     * Explicitly ignores other ini keys.
     *
     * @param array $options Session ini directives [key => value]
     *
     * @see https://php.net/session.configuration
     */
    public function set_options(array $options): void
    {
        if (headers_sent() || \PHP_SESSION_ACTIVE === session_status()) {
            return;
        }
        $valid_options = array_flip(['cache_expire', 'cache_limiter', 'cookie_domain', 'cookie_httponly', 'cookie_lifetime', 'cookie_path', 'cookie_secure', 'cookie_samesite', 'gc_divisor', 'gc_maxlifetime', 'gc_probability', 'lazy_write', 'name', 'serialize_handler', 'use_strict_mode', 'use_cookies']);
        foreach ($options as $key => $value) {
            if (isset($valid_options[$key])) {
                if ('cookie_secure' === $key && 'auto' === $value) {
                    continue;
                }
                ini_set('session.' . $key, $value);
            }
        }
    }
    /**
     * Registers session save handler as a PHP session handler.
     *
     * To use internal PHP session save handlers, override this method using ini_set with
     * session.save_handler and session.save_path e.g.
     *
     *     ini_set('session.save_handler', 'files');
     *     ini_set('session.save_path', '/tmp');
     *
     * or pass in a \SessionHandler instance which configures session.save_handler in the
     * constructor, for a template see NativeFileSessionHandler.
     *
     * @see https://php.net/session-set-save-handler
     * @see https://php.net/sessionhandlerinterface
     * @see https://php.net/sessionhandler
     *
     * @throws \InvalidArgumentException
     */
    public function set_save_handler(Abstract_Proxy|\Session_Handler_Interface|null $save_handler): void
    {
        // Wrap $saveHandler in proxy and prevent double wrapping of proxy
        if (!$save_handler instanceof Abstract_Proxy && $save_handler instanceof \Session_Handler_Interface) {
            $save_handler = new Session_Handler_Proxy($save_handler);
        } elseif (!$save_handler instanceof Abstract_Proxy) {
            $save_handler = new Session_Handler_Proxy(new Strict_Session_Handler(new \Session_Handler()));
        }
        $this->save_handler = $save_handler;
        if (headers_sent() || \PHP_SESSION_ACTIVE === session_status()) {
            return;
        }
        if ($this->save_handler instanceof Session_Handler_Proxy) {
            session_set_save_handler($this->save_handler, false);
        }
    }
    /**
     * Load the session with attributes.
     *
     * After starting the session, PHP retrieves the session from whatever handlers
     * are set to (either PHP's internal, or a custom save handler set with session_set_save_handler()).
     * PHP takes the return value from the read() handler, unserializes it
     * and populates $_SESSION with the result automatically.
     */
    protected function load_session(?array &$session = null): void
    {
        if (null === $session) {
            $session =& $_SESSION;
        }
        $bags = array_merge($this->bags, [$this->metadata_bag]);
        foreach ($bags as $bag) {
            $key = $bag->get_storage_key();
            $session[$key] = isset($session[$key]) && \is_array($session[$key]) ? $session[$key] : [];
            $bag->initialize($session[$key]);
        }
        $this->started = true;
        $this->closed = false;
    }
}