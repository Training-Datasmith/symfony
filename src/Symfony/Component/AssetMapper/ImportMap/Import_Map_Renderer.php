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
namespace Symfony\Component\Asset_Mapper\Import_Map;

use Psr\Link\Evolvable_Link_Provider_Interface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Web_Link\Event_Listener\Add_Link_Header_Listener;
use Symfony\Component\Web_Link\Generic_Link_Provider;
use Symfony\Component\Web_Link\Link;
/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class Import_Map_Renderer
{
    // https://generator.jspm.io/#S2NnYGAIzSvJLMlJTWEAAMYOgCAOAA
    private const DEFAULT_ES_MODULE_SHIMS_POLYFILL_URL = 'https://ga.jspm.io/npm:es-module-shims@1.10.0/dist/es-module-shims.js';
    private const DEFAULT_ES_MODULE_SHIMS_POLYFILL_INTEGRITY = 'sha384-ie1x72Xck445i0j4SlNJ5W5iGeL3Dpa0zD48MZopgWsjNB/lt60SuG1iduZGNnJn';
    private const LOADER_JSON = "export default (async()=>await(await fetch('%s')).json())()";
    private const LOADER_CSS = "document.head.appendChild(Object.assign(document.createElement('link'),{rel:'stylesheet',href:'%s'}))";
    public function __construct(private readonly Import_Map_Generator $import_map_generator, private readonly ?Packages $asset_packages = null, private readonly string $charset = 'UTF-8', private readonly string|false $polyfill_import_name = false, private readonly array $script_attributes = [], private readonly ?Request_Stack $request_stack = null)
    {
    }
    public function render(string|array $entry_point, array $attributes = []): string
    {
        $entry_point = (array) $entry_point;
        $import_map_data = $this->import_map_generator->get_import_map_data($entry_point);
        $import_map = [];
        $module_preloads = [];
        $web_links = [];
        $polyfill_path = null;
        foreach ($import_map_data as $import_name => $data) {
            $path = $data['path'];
            if ($this->asset_packages) {
                // ltrim so the subdirectory (if needed) can be prepended
                $path = $this->asset_packages->get_url(ltrim($path, '/'));
            }
            // if this represents the polyfill, hide it from the import map
            if ($import_name === $this->polyfill_import_name) {
                $polyfill_path = $path;
                continue;
            }
            // for subdirectories or CDNs, the import name needs to be the full URL
            if (str_starts_with($import_name, '/') && $this->asset_packages) {
                $import_name = $this->asset_packages->get_url(ltrim($import_name, '/'));
            }
            $preload = $data['preload'] ?? false;
            if ('json' === $data['type']) {
                $import_map[$import_name] = 'data:application/javascript,' . str_replace('%', '%25', \sprintf(self::LOADER_JSON, addslashes($path)));
                if ($preload) {
                    $web_links[$path] = 'fetch';
                }
            } elseif ('css' !== $data['type']) {
                $import_map[$import_name] = $path;
                if ($preload) {
                    $module_preloads[$path] = $path;
                }
            } elseif ($preload) {
                $web_links[$path] = 'style';
                // importmap entry is a noop
                $import_map[$import_name] = 'data:application/javascript,';
            } else {
                $import_map[$import_name] = 'data:application/javascript,' . str_replace('%', '%25', \sprintf(self::LOADER_CSS, addslashes($path)));
            }
        }
        $output = '';
        foreach ($web_links as $url => $as) {
            if ('style' === $as) {
                $output .= "\n<link rel=\"stylesheet\" href=\"{$this->escape_attribute_value($url)}\">";
            }
        }
        if (class_exists(Add_Link_Header_Listener::class) && $request = $this->request_stack?->get_current_request()) {
            $this->add_web_link_preloads($request, $web_links);
        }
        $script_attributes = $attributes || $this->script_attributes ? ' ' . $this->create_attributes_string($attributes) : '';
        $import_map_json = json_encode(['imports' => $import_map], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG);
        $output .= <<<HTML
        
        <script type="importmap"{$script_attributes}>
        {$import_map_json}
        </script>
        HTML;
        if (false !== $this->polyfill_import_name && null === $polyfill_path) {
            if ('es-module-shims' !== $this->polyfill_import_name) {
                throw new \InvalidArgumentException(\sprintf('The JavaScript module polyfill was not found in your import map. Either disable the polyfill or run "php bin/console importmap:require "%s"" to install it.', $this->polyfill_import_name));
            }
            // a fallback for the default polyfill in case it's not in the importmap
            $polyfill_path = self::DEFAULT_ES_MODULE_SHIMS_POLYFILL_URL;
        }
        if ($polyfill_path) {
            $polyfill_attributes = $attributes + $this->script_attributes;
            // Add security attributes for the default polyfill hosted on jspm.io
            if (self::DEFAULT_ES_MODULE_SHIMS_POLYFILL_URL === $polyfill_path) {
                $polyfill_attributes = ['crossorigin' => 'anonymous', 'integrity' => self::DEFAULT_ES_MODULE_SHIMS_POLYFILL_INTEGRITY] + $polyfill_attributes;
            }
            $output .= <<<HTML
            <script{$script_attributes}>
            if (!HTMLScriptElement.supports || !HTMLScriptElement.supports('importmap')) (function () {
                const script = document.createElement('script');
                script.src = '{$this->escape_attribute_value($polyfill_path, \ENT_NOQUOTES)}';
                {$this->create_attributes_string($polyfill_attributes, "script.setAttribute('%s', '%s');", "\n    ", \ENT_NOQUOTES)}
                document.head.appendChild(script);
            })();
            </script>
            HTML;
        }
        foreach ($module_preloads as $url) {
            $url = $this->escape_attribute_value($url);
            $output .= "\n<link rel=\"modulepreload\" href=\"{$url}\">";
        }
        if (\count($entry_point) > 0) {
            $output .= "\n<script type=\"module\"{$script_attributes}>";
            foreach ($entry_point as $entry_point_name) {
                $entry_point_name = $this->escape_attribute_value($entry_point_name);
                $output .= "import '" . str_replace("'", "\\'", $entry_point_name) . "';";
            }
            $output .= '</script>';
        }
        return $output;
    }
    private function escape_attribute_value(string $value, int $flags = \ENT_COMPAT | \ENT_SUBSTITUTE): string
    {
        $value = htmlspecialchars($value, $flags, $this->charset);
        return \ENT_NOQUOTES & $flags ? addslashes($value) : $value;
    }
    private function create_attributes_string(array $attributes, string $pattern = '%s="%s"', string $glue = ' ', int $flags = \ENT_COMPAT | \ENT_SUBSTITUTE): string
    {
        $attribute_string = '';
        $attributes += $this->script_attributes;
        if (isset($attributes['src']) || isset($attributes['type'])) {
            throw new \InvalidArgumentException(\sprintf('The "src" and "type" attributes are not allowed on the <script> tag rendered by "%s".', self::class));
        }
        foreach ($attributes as $name => $value) {
            if ('' !== $attribute_string) {
                $attribute_string .= $glue;
            }
            if (true === $value) {
                $value = $name;
            }
            $attribute_string .= \sprintf($pattern, $this->escape_attribute_value($name, $flags), $this->escape_attribute_value($value, $flags));
        }
        return preg_replace('/\b([^ =]++)="\1"/', '\1', $attribute_string);
    }
    private function add_web_link_preloads(Request $request, array $links): void
    {
        foreach ($links as $url => $as) {
            $links[$url] = (new Link('preload', $url))->with_attribute('as', $as);
            if ('fetch' === $as) {
                $links[$url] = $links[$url]->with_attribute('crossorigin', 'anonymous');
            }
        }
        if (null === $link_provider = $request->attributes->get('_links')) {
            $request->attributes->set('_links', new Generic_Link_Provider($links));
            return;
        }
        if (!$link_provider instanceof Evolvable_Link_Provider_Interface) {
            return;
        }
        foreach ($links as $link) {
            $link_provider = $link_provider->with_link($link);
        }
        $request->attributes->set('_links', $link_provider);
    }
}