<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Request;

// Help opcache.preload discover always-needed symbols
class_exists(MockFileSessionStorage::class);

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class MockFileSessionStorageFactory implements SessionStorageFactoryInterface
{
    /**
     * @see MockFileSessionStorage constructor.
     */
    public function __construct(
        private readonly ?string $savePath = null,
        private readonly string $name = 'MOCKSESSID',
        private readonly ?MetadataBag $metaBag = null,
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        return new MockFileSessionStorage($this->savePath, $this->name, $this->metaBag);
    }
}
