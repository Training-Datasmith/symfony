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
namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Traits\Filesystem_Trait;
class Filesystem_Adapter extends Abstract_Adapter implements Pruneable_Interface
{
    use Filesystem_Trait;
    public function __construct(string $namespace = '', int $default_lifetime = 0, ?string $directory = null)
    {
        parent::__construct('', $default_lifetime);
        $this->init($namespace, $directory);
    }
}