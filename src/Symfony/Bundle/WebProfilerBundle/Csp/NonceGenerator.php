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
namespace Symfony\Bundle\Web_Profiler_Bundle\Csp;

/**
 * Generates Content-Security-Policy nonce.
 *
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @internal
 */
class Nonce_Generator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}