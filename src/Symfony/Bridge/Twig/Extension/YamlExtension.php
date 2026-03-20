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

use Symfony\Component\Yaml\Dumper as YamlDumper;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Filter;
/**
 * Provides integration of the Yaml component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Yaml_Extension extends Abstract_Extension
{
    public function get_filters(): array
    {
        return [new Twig_Filter('yaml_encode', $this->encode(...)), new Twig_Filter('yaml_dump', $this->dump(...))];
    }
    public function encode(mixed $input, int $inline = 0, int $dump_objects = 0): string
    {
        static $dumper;
        $dumper ??= new Yaml_Dumper();
        return $dumper->dump($input, $inline, 0, $dump_objects);
    }
    public function dump(mixed $value, int $inline = 0, int $dump_objects = 0): string
    {
        if (\is_resource($value)) {
            return '%Resource%';
        }
        if (\is_array($value) || \is_object($value)) {
            return '%' . \gettype($value) . '% ' . $this->encode($value, $inline, $dump_objects);
        }
        return $this->encode($value, $inline, $dump_objects);
    }
}