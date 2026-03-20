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
namespace Symfony\Bundle\Web_Profiler_Bundle;

use Symfony\Component\Http_Kernel\Bundle\Bundle;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Web_Profiler_Bundle extends Bundle
{
    public function boot(): void
    {
        if ('prod' === $this->container->get_parameter('kernel.environment')) {
            @trigger_error('Using WebProfilerBundle in production is not supported and puts your project at risk, disable it.', \E_USER_WARNING);
        }
    }
}