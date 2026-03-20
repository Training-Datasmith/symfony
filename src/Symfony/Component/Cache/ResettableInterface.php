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
namespace Symfony\Component\Cache;

use Symfony\Contracts\Service\Reset_Interface;
/**
 * Resets a pool's local state.
 */
interface Resettable_Interface extends Reset_Interface
{
}