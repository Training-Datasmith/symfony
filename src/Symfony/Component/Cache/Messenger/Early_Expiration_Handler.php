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
namespace Symfony\Component\Cache\Messenger;

use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Dependency_Injection\Reverse_Container;
use Symfony\Component\Messenger\Attribute\As_Message_Handler;
/**
 * Computes cached values sent to a message bus.
 */
#[As_Message_Handler]
class Early_Expiration_Handler
{
    private array $processed_nonces = [];
    public function __construct(private readonly Reverse_Container $reverse_container)
    {
    }
    public function __invoke(Early_Expiration_Message $message): void
    {
        $item = $message->get_item();
        $metadata = $item->get_metadata();
        $expiry = $metadata[Cache_Item::METADATA_EXPIRY] ?? 0;
        $ctime = $metadata[Cache_Item::METADATA_CTIME] ?? 0;
        if ($expiry && $ctime) {
            // skip duplicate or expired messages
            $processing_nonce = [$expiry, $ctime];
            $pool = $message->get_pool();
            $key = $item->get_key();
            if (($this->processed_nonces[$pool][$key] ?? null) === $processing_nonce) {
                return;
            }
            if (microtime(true) >= $expiry) {
                return;
            }
            $this->processed_nonces[$pool] = [$key => $processing_nonce] + ($this->processed_nonces[$pool] ?? []);
            if (\count($this->processed_nonces[$pool]) > 100) {
                array_pop($this->processed_nonces[$pool]);
            }
        }
        static $set_metadata;
        $set_metadata ??= \Closure::bind(static function (Cache_Item $item, float $start_time): void {
            if ($item->expiry > $end_time = microtime(true)) {
                $item->new_metadata[Cache_Item::METADATA_EXPIRY] = $item->expiry;
                $item->new_metadata[Cache_Item::METADATA_CTIME] = (int) ceil(1000 * ($end_time - $start_time));
            }
        }, null, Cache_Item::class);
        $start_time = microtime(true);
        $pool = $message->find_pool($this->reverse_container);
        $callback = $message->find_callback($this->reverse_container);
        $save = true;
        $value = $callback($item, $save);
        $set_metadata($item, $start_time);
        $pool->save($item->set($value));
    }
}