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
namespace Symfony\Bundle\Twig_Bundle;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * Iterator for all templates in bundles and in the application Resources directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 *
 * @implements \IteratorAggregate<int, string>
 */
class Template_Iterator implements \IteratorAggregate
{
    private \Traversable $templates;
    /**
     * @param array       $paths        Additional Twig paths to warm
     * @param string|null $defaultPath  The directory where global templates can be stored
     * @param string[]    $namePatterns Pattern of file names
     */
    public function __construct(private readonly Kernel_Interface $kernel, private readonly array $paths = [], private readonly ?string $default_path = null, private readonly array $name_patterns = [])
    {
    }
    public function getIterator(): \Traversable
    {
        if (isset($this->templates)) {
            return $this->templates;
        }
        $templates = null !== $this->default_path ? [$this->find_templates_in_directory($this->default_path, null, ['bundles'])] : [];
        foreach ($this->kernel->get_bundles() as $bundle) {
            $name = $bundle->get_name();
            if (str_ends_with($name, 'Bundle')) {
                $name = substr($name, 0, -6);
            }
            $bundle_templates_dir = is_dir($bundle->get_path() . '/Resources/views') ? $bundle->get_path() . '/Resources/views' : $bundle->get_path() . '/templates';
            $templates[] = $this->find_templates_in_directory($bundle_templates_dir, $name);
            if (null !== $this->default_path) {
                $templates[] = $this->find_templates_in_directory($this->default_path . '/bundles/' . $bundle->get_name(), $name);
            }
            /*
             * The bundle's own templates are also registered with the "!" prefix namespace - this matches
             * @see \Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension::load()
             */
            $templates[] = $this->find_templates_in_directory($bundle_templates_dir, '!' . $name);
        }
        foreach ($this->paths as $dir => $namespace) {
            $templates[] = $this->find_templates_in_directory($dir, $namespace);
        }
        return $this->templates = new \ArrayIterator(array_unique(array_merge([], ...$templates)));
    }
    /**
     * Find templates in the given directory.
     *
     * @return string[]
     */
    private function find_templates_in_directory(string $dir, ?string $namespace = null, array $exclude_dirs = []): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $templates = [];
        foreach (Finder::create()->files()->follow_links()->in($dir)->exclude($exclude_dirs)->name($this->name_patterns) as $file) {
            $templates[] = (null !== $namespace ? '@' . $namespace . '/' : '') . str_replace('\\', '/', $file->get_relative_pathname());
        }
        return $templates;
    }
}