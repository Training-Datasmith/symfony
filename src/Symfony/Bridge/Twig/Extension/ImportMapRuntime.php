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

use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Renderer;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class Import_Map_Runtime
{
    public function __construct(private readonly Import_Map_Renderer $import_map_renderer)
    {
    }
    public function importmap(string|array $entry_point = 'app', array $attributes = []): string
    {
        return $this->import_map_renderer->render($entry_point, $attributes);
    }
}