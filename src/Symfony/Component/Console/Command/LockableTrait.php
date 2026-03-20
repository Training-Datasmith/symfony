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
namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Lock\Lock_Factory;
use Symfony\Component\Lock\Lock_Interface;
use Symfony\Component\Lock\Store\Flock_Store;
use Symfony\Component\Lock\Store\Semaphore_Store;
/**
 * Basic lock feature for commands.
 *
 * @author Geoffrey Brier <geoffrey.brier@gmail.com>
 */
trait Lockable_Trait
{
    private ?Lock_Interface $lock = null;
    private ?Lock_Factory $lock_factory = null;
    /**
     * Locks a command.
     */
    private function lock(?string $name = null, bool $blocking = false): bool
    {
        if (!class_exists(Semaphore_Store::class)) {
            throw new LogicException('To enable the locking feature you must install the symfony/lock component. Try running "composer require symfony/lock".');
        }
        if (null !== $this->lock) {
            throw new LogicException('A lock is already in place.');
        }
        if (null === $this->lock_factory) {
            if (Semaphore_Store::is_supported()) {
                $store = new Semaphore_Store();
            } else {
                $store = new Flock_Store();
            }
            $this->lock_factory = new Lock_Factory($store);
        }
        if (!$name) {
            if ($this instanceof Command) {
                $name = $this->get_name();
            } elseif ($attribute = (new \ReflectionClass($this::class))->get_attributes(As_Command::class)) {
                $name = $attribute[0]->new_instance()->name;
            } else {
                throw new LogicException(\sprintf('Lock name missing: provide it via "%s()", #[AsCommand] attribute, or by extending Command class.', __METHOD__));
            }
        }
        $this->lock = $this->lock_factory->create_lock($name);
        if (!$this->lock->acquire($blocking)) {
            $this->lock = null;
            return false;
        }
        return true;
    }
    /**
     * Releases the command lock if there is one.
     */
    private function release(): void
    {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }
}