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
namespace Symfony\Component\Asset_Mapper\Compiler;

use Psr\Log\Logger_Interface;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Compiler\Parser\Javascript_Sequence_Parser;
use Symfony\Component\Asset_Mapper\Exception\Circular_Assets_Exception;
use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Config_Reader;
use Symfony\Component\Asset_Mapper\Import_Map\Java_Script_Import;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
use Symfony\Component\Filesystem\Path;
/**
 * Resolves import paths in JS files.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final readonly class Java_Script_Import_Path_Compiler implements Asset_Compiler_Interface
{
    /**
     * @see https://regex101.com/r/1iBAIb/2
     */
    private const IMPORT_PATTERN = '/
            ^(?:\/\/.*)                     # Lines that start with comments
        |
            (?:
                \'(?:[^\'\\\\\\n]|\\\\.)*+\'   # Strings enclosed in single quotes
            |
                "(?:[^"\\\\\\n]|\\\\.)*+"      # Strings enclosed in double quotes
            )
        |
            (?:                            # Import statements (script captured)
                import\s*
                    (?:
                        (?:\*\s*as\s+\w+|\s+[\w\s{},*]+)
                        \s*from\s*
                    )?
            |
                \bimport\(
            )
            \s*[\'"`](\.\/[^\'"`\n]++|(\.\.\/)*+[^\'"`\n]++)[\'"`]\s*[;\)]
        ?
    /mxu';
    public function __construct(private Import_Map_Config_Reader $import_map_config_reader, private string $missing_import_mode = self::MISSING_IMPORT_WARN, private ?Logger_Interface $logger = null)
    {
    }
    public function compile(string $content, Mapped_Asset $asset, Asset_Mapper_Interface $asset_mapper): string
    {
        $js_parser = new Javascript_Sequence_Parser($content);
        return preg_replace_callback(self::IMPORT_PATTERN, function ($matches) use ($asset, $asset_mapper, $js_parser): string {
            $full_import_string = $matches[0][0];
            $js_parser->parse_until($matches[0][1]);
            if (!$js_parser->is_executable()) {
                return $full_import_string;
            }
            $imported_module = $matches[1][0];
            // we don't support absolute paths, so ignore completely
            if (str_starts_with($imported_module, '/')) {
                return $full_import_string;
            }
            $is_relative_import = str_starts_with($imported_module, '.');
            if (!$is_relative_import) {
                // URL or /absolute imports will also go here, but will be ignored
                $dependent_asset = $this->find_asset_for_bare_import($imported_module, $asset_mapper);
            } else {
                $dependent_asset = $this->find_asset_for_relative_import($imported_module, $asset, $asset_mapper);
            }
            if (!$dependent_asset) {
                return $full_import_string;
            }
            // Ignore self-referencing import
            if ($dependent_asset->logical_path === $asset->logical_path) {
                return $full_import_string;
            }
            // List as a JavaScript import.
            // This will cause the asset to be included in the importmap (for relative imports)
            // and will be used to generate the preloads in the importmap.
            $is_lazy = str_contains($full_import_string, 'import(');
            $add_to_import_map = $is_relative_import;
            $asset->add_java_script_import(new Java_Script_Import($add_to_import_map ? $dependent_asset->public_path_without_digest : $imported_module, $dependent_asset->logical_path, $dependent_asset->source_path, $is_lazy, $add_to_import_map));
            if (!$add_to_import_map) {
                // only (potentially) adjust for automatic relative imports
                return $full_import_string;
            }
            // support possibility where the final public files have moved relative to each other
            $relative_import_path = Path::make_relative($dependent_asset->public_path_without_digest, \dirname($asset->public_path_without_digest));
            $relative_import_path = $this->make_relative_for_java_script($relative_import_path);
            return str_replace($imported_module, $relative_import_path, $full_import_string);
        }, $content, -1, $count, \PREG_OFFSET_CAPTURE) ?? throw new RuntimeException(\sprintf('Failed to compile JavaScript import paths in "%s". Error: "%s".', $asset->source_path, preg_last_error_msg()));
    }
    public function supports(Mapped_Asset $asset): bool
    {
        return 'js' === $asset->public_extension;
    }
    private function make_relative_for_java_script(string $path): string
    {
        if (str_starts_with($path, '../')) {
            return $path;
        }
        return './' . $path;
    }
    private function handle_missing_import(string $message, ?\Throwable $e = null): void
    {
        match ($this->missing_import_mode) {
            Asset_Compiler_Interface::MISSING_IMPORT_IGNORE => null,
            Asset_Compiler_Interface::MISSING_IMPORT_WARN => $this->logger?->warning($message),
            Asset_Compiler_Interface::MISSING_IMPORT_STRICT => throw new RuntimeException($message, 0, $e),
        };
    }
    private function find_asset_for_bare_import(string $imported_module, Asset_Mapper_Interface $asset_mapper): ?Mapped_Asset
    {
        if (!$import_map_entry = $this->import_map_config_reader->find_root_import_map_entry($imported_module)) {
            // don't warn on missing non-relative (bare) imports: these could be valid URLs
            return null;
        }
        try {
            if ($asset = $asset_mapper->get_asset($import_map_entry->path)) {
                return $asset;
            }
            return $asset_mapper->get_asset_from_source_path($this->import_map_config_reader->convert_path_to_filesystem_path($import_map_entry->path));
        } catch (Circular_Assets_Exception $exception) {
            return $exception->get_incomplete_mapped_asset();
        }
    }
    private function find_asset_for_relative_import(string $imported_module, Mapped_Asset $asset, Asset_Mapper_Interface $asset_mapper): ?Mapped_Asset
    {
        try {
            $resolved_source_path = Path::join(\dirname($asset->source_path), $imported_module);
        } catch (RuntimeException $e) {
            // avoid warning about vendor imports - these are often comments
            if (!$asset->is_vendor) {
                $this->handle_missing_import(\sprintf('Error processing import in "%s": ', $asset->source_path) . $e->get_message(), $e);
            }
            return null;
        }
        try {
            $dependent_asset = $asset_mapper->get_asset_from_source_path($resolved_source_path);
        } catch (Circular_Assets_Exception $exception) {
            $dependent_asset = $exception->get_incomplete_mapped_asset();
        }
        if ($dependent_asset) {
            return $dependent_asset;
        }
        // avoid warning about vendor imports - these are often comments
        if ($asset->is_vendor) {
            return null;
        }
        $message = \sprintf('Unable to find asset "%s" imported from "%s".', $imported_module, $asset->source_path);
        if (is_file($resolved_source_path)) {
            $message .= \sprintf('The file "%s" exists, but it is not in a mapped asset path. Add it to the "paths" config.', $resolved_source_path);
        } else {
            try {
                if (null !== $asset_mapper->get_asset_from_source_path(\sprintf('%s.js', $resolved_source_path))) {
                    $message .= \sprintf(' Try adding ".js" to the end of the import - i.e. "%s.js".', $imported_module);
                }
            } catch (Circular_Assets_Exception) {
                // avoid circular error if there is self-referencing import comments
            }
        }
        $this->handle_missing_import($message);
        return null;
    }
}