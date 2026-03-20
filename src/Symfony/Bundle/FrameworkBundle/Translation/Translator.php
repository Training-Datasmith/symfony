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
namespace Symfony\Bundle\Framework_Bundle\Translation;

use Psr\Container\Container_Interface;
use Symfony\Component\Config\Resource\Directory_Resource;
use Symfony\Component\Config\Resource\File_Existence_Resource;
use Symfony\Component\Http_Kernel\Cache_Warmer\Warmable_Interface;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Formatter\Message_Formatter_Interface;
use Symfony\Component\Translation\Translator as BaseTranslator;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Translator extends Base_Translator implements Warmable_Interface
{
    protected array $options = ['cache_dir' => null, 'debug' => false, 'resource_files' => [], 'scanned_directories' => [], 'cache_vary' => []];
    /**
     * @var list<string>
     */
    private readonly array $resource_locales;
    /**
     * Holds parameters from addResource() calls so we can defer the actual
     * parent::addResource() calls until initialize() is executed.
     *
     * @var array[]
     */
    private array $resources = [];
    /**
     * @var string[][]
     */
    private array $resource_files;
    /**
     * @var string[]
     */
    private readonly array $scanned_directories;
    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir:      The cache directory (or null to disable caching)
     *   * debug:          Whether to enable debugging or not (false by default)
     *   * resource_files: List of translation resources available grouped by locale.
     *   * cache_vary:     An array of data that is serialized to generate the cached catalogue name.
     *
     * @param string[] $enabledLocales
     *
     * @throws InvalidArgumentException
     */
    public function __construct(protected Container_Interface $container, Message_Formatter_Interface $formatter, string $default_locale, protected array $loader_ids = [], array $options = [], private readonly array $enabled_locales = [])
    {
        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new InvalidArgumentException(\sprintf('The Translator does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }
        $this->options = array_merge($this->options, $options);
        $this->resource_locales = array_keys($this->options['resource_files']);
        $this->resource_files = $this->options['resource_files'];
        $this->scanned_directories = $this->options['scanned_directories'];
        parent::__construct($default_locale, $formatter, $this->options['cache_dir'], $this->options['debug'], $this->options['cache_vary']);
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        // skip warmUp when translator doesn't use cache
        if (null === $this->options['cache_dir']) {
            return [];
        }
        $locales_to_warm_up = $this->enabled_locales ?: array_merge($this->get_fallback_locales(), [$this->get_locale()], $this->resource_locales);
        foreach (array_unique($locales_to_warm_up) as $locale) {
            // reset catalogue in case it's already loaded during the dump of the other locales.
            if (isset($this->catalogues[$locale])) {
                unset($this->catalogues[$locale]);
            }
            $this->load_catalogue($locale);
        }
        return [];
    }
    public function add_resource(string $format, mixed $resource, string $locale, ?string $domain = null): void
    {
        if ($this->resource_files) {
            $this->add_resource_files();
        }
        $this->resources[] = [$format, $resource, $locale, $domain];
    }
    protected function initialize_catalogue(string $locale): void
    {
        $this->initialize();
        parent::initialize_catalogue($locale);
    }
    /**
     * @internal
     */
    protected function do_load_catalogue(string $locale): void
    {
        parent::do_load_catalogue($locale);
        foreach ($this->scanned_directories as $directory) {
            $resource_class = file_exists($directory) ? Directory_Resource::class : File_Existence_Resource::class;
            $this->catalogues[$locale]->add_resource(new $resource_class($directory));
        }
    }
    protected function initialize(): void
    {
        if ($this->resource_files) {
            $this->add_resource_files();
        }
        foreach ($this->resources as $params) {
            [$format, $resource, $locale, $domain] = $params;
            parent::add_resource($format, $resource, $locale, $domain);
        }
        $this->resources = [];
        foreach ($this->loader_ids as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->add_loader($alias, $this->container->get($id));
            }
        }
    }
    private function add_resource_files(): void
    {
        $files_by_locale = $this->resource_files;
        $this->resource_files = [];
        foreach ($files_by_locale as $files) {
            foreach ($files as $file) {
                // filename is domain.locale.format
                $file_name_parts = explode('.', basename($file));
                $format = array_pop($file_name_parts);
                $locale = array_pop($file_name_parts);
                $domain = implode('.', $file_name_parts);
                $this->add_resource($format, $file, $locale, $domain);
            }
        }
    }
}