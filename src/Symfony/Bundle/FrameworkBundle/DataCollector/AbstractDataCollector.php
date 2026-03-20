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

use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
abstract class Abstract_Data_Collector extends Data_Collector implements Template_Aware_Data_Collector_Interface
{
    public function get_name(): string
    {
        return static::class;
    }
    public static function get_template(): ?string
    {
        return null;
    }
}