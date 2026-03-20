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
namespace Symfony\Bridge\Twig\Extension;

use Twig\Extension\Abstract_Extension;
use Twig\Twig_Function;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class Import_Map_Extension extends Abstract_Extension
{
    public function get_functions(): array
    {
        return [new Twig_Function('importmap', [Import_Map_Runtime::class, 'importmap'], ['is_safe' => ['html']])];
    }
}