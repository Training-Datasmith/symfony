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
namespace Symfony\Component\Http_Client\Response;

use Guzzle_Http\Promise\Create;
use Guzzle_Http\Promise\Promise_Interface as GuzzlePromiseInterface;
use Http\Promise\Promise as HttplugPromiseInterface;
use Psr\Http\Message\Response_Interface as Psr7ResponseInterface;
/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 */
final readonly class Httplug_Promise implements Httplug_Promise_Interface
{
    public function __construct(private Guzzle_Promise_Interface $promise)
    {
    }
    public function then(?callable $on_fulfilled = null, ?callable $on_rejected = null): self
    {
        return new self($this->promise->then($this->wrap_then_callback($on_fulfilled), $this->wrap_then_callback($on_rejected)));
    }
    public function cancel(): void
    {
        $this->promise->cancel();
    }
    public function get_state(): string
    {
        return $this->promise->get_state();
    }
    /**
     * @return Psr7ResponseInterface|mixed
     */
    public function wait($unwrap = true): mixed
    {
        $result = $this->promise->wait($unwrap);
        while ($result instanceof Httplug_Promise_Interface || $result instanceof Guzzle_Promise_Interface) {
            $result = $result->wait($unwrap);
        }
        return $result;
    }
    private function wrap_then_callback(?callable $callback): ?callable
    {
        if (null === $callback) {
            return null;
        }
        return static fn($value) => Create::promise_for($callback($value));
    }
}