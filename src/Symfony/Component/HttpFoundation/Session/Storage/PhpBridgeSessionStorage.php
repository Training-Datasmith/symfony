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

use Symfony\Component\Http_Foundation\Session\Storage\Proxy\Abstract_Proxy;
/**
 * Allows session to be started by PHP and managed by Symfony.
 *
 * @author Drak <drak@zikula.org>
 */
class Php_Bridge_Session_Storage extends Native_Session_Storage
{
    public function __construct(Abstract_Proxy|\Session_Handler_Interface|null $handler = null, ?Metadata_Bag $meta_bag = null)
    {
        if (!\extension_loaded('session')) {
            throw new \LogicException('PHP extension "session" is required.');
        }
        $this->set_metadata_bag($meta_bag);
        $this->set_save_handler($handler);
    }
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }
        $this->load_session();
        return true;
    }
    public function clear(): void
    {
        // clear out the bags and nothing else that may be set
        // since the purpose of this driver is to share a handler
        foreach ($this->bags as $bag) {
            $bag->clear();
        }
        // reconnect the bags to the session
        $this->load_session();
    }
}