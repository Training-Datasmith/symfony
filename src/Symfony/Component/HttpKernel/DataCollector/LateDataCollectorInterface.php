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
namespace Symfony\Component\Http_Kernel\Data_Collector;

/**
 * LateDataCollectorInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Late_Data_Collector_Interface
{
    /**
     * Collects data as late as possible.
     */
    public function late_collect(): void;
}