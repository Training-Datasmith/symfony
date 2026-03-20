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
namespace Symfony\Bundle\Framework_Bundle\Data_Collector;

use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector_Interface;
/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
interface Template_Aware_Data_Collector_Interface extends Data_Collector_Interface
{
    public static function get_template(): ?string;
}