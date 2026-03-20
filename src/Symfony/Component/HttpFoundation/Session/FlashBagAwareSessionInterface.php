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
namespace Symfony\Component\Http_Foundation\Session;

use Symfony\Component\Http_Foundation\Session\Flash\Flash_Bag_Interface;
/**
 * Interface for session with a flashbag.
 */
interface Flash_Bag_Aware_Session_Interface extends Session_Interface
{
    public function get_flash_bag(): Flash_Bag_Interface;
}