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

use Psr\Log\Logger_Interface;
use Symfony\Component\Cache\Adapter\Adapter_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Dependency_Injection\Reverse_Container;
use Symfony\Component\Messenger\Message_Bus_Interface;
use Symfony\Component\Messenger\Stamp\Handled_Stamp;
/**
 * Sends the computation of cached values to a message bus.
 */
class Early_Expiration_Dispatcher
{
    private readonly ?\Closure $callback_wrapper;
    public function __construct(private readonly Message_Bus_Interface $bus, private readonly Reverse_Container $reverse_container, ?callable $callback_wrapper = null)
    {
        $this->callback_wrapper = null === $callback_wrapper ? null : $callback_wrapper(...);
    }
    public function __invoke(callable $callback, Cache_Item $item, bool &$save, Adapter_Interface $pool, \Closure $set_metadata, ?Logger_Interface $logger = null, ?float $beta = null): mixed
    {
        if (!$item->is_hit() || null === $message = Early_Expiration_Message::create($this->reverse_container, $callback, $item, $pool)) {
            // The item is stale or the callback cannot be reversed: we must compute the value now
            $logger?->info('Computing item "{key}" online: ' . ($item->is_hit() ? 'callback cannot be reversed' : 'item is stale'), ['key' => $item->get_key()]);
            return null !== $this->callback_wrapper ? ($this->callback_wrapper)($callback, $item, $save, $pool, $set_metadata, $logger, $beta) : $callback($item, $save);
        }
        $envelope = $this->bus->dispatch($message);
        if ($logger) {
            if ($envelope->last(Handled_Stamp::class)) {
                $logger->info('Item "{key}" was computed online', ['key' => $item->get_key()]);
            } else {
                $logger->info('Item "{key}" sent for recomputation', ['key' => $item->get_key()]);
            }
        }
        // The item's value is not stale, no need to write it to the backend
        $save = false;
        return $message->get_item()->get() ?? $item->get();
    }
}