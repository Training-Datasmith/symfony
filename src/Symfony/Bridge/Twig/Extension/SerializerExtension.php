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
use Twig\Twig_Filter;
/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
final class Serializer_Extension extends Abstract_Extension
{
    public function get_filters(): array
    {
        return [new Twig_Filter('serialize', [Serializer_Runtime::class, 'serialize'])];
    }
}