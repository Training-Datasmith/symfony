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

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Session\Storage\Proxy\Abstract_Proxy;
// Help opcache.preload discover always-needed symbols
class_exists(Native_Session_Storage::class);
/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class Native_Session_Storage_Factory implements Session_Storage_Factory_Interface
{
    /**
     * @see NativeSessionStorage constructor.
     */
    public function __construct(private readonly array $options = [], private readonly Abstract_Proxy|\Session_Handler_Interface|null $handler = null, private readonly ?Metadata_Bag $meta_bag = null, private readonly bool $secure = false)
    {
    }
    public function create_storage(?Request $request): Session_Storage_Interface
    {
        $storage = new Native_Session_Storage($this->options, $this->handler, $this->meta_bag);
        if ($this->secure && $request?->is_secure()) {
            $storage->set_options(['cookie_secure' => true]);
        }
        return $storage;
    }
}