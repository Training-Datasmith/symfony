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
use Symfony\Component\Asset_Mapper\Exception\RuntimeException;
use Symfony\Component\Asset_Mapper\Mapped_Asset;
use Symfony\Component\Filesystem\Path;
/**
 * Resolves url() paths in CSS files.
 *
 * Originally sourced from https://github.com/rails/propshaft/blob/main/lib/propshaft/compiler/css_asset_urls.rb
 */
final readonly class Css_Asset_Url_Compiler implements Asset_Compiler_Interface
{
    // https://regex101.com/r/BOJ3vG/2
    public const ASSET_URL_PATTERN = <<<'REGEX'
    {
        (?|
        (url\()\s*+["']?(?!(?:/|\#|%23|data|http|//))([^"')\s?#]++)(?:[?#][^"')]++)?["']?\s*+(\))
        |
        (@import\s++)["'](?!(?:/|\#|%23|data|http|//))([^"')\s?#]++)(?:[?#][^"')]++)?["']
        )
    }x
    REGEX;
    public function __construct(private string $missing_import_mode = self::MISSING_IMPORT_WARN, private ?Logger_Interface $logger = null)
    {
    }
    public function compile(string $content, Mapped_Asset $asset, Asset_Mapper_Interface $asset_mapper): string
    {
        preg_match_all('/\/\*|\*\//', $content, $comment_matches, \PREG_OFFSET_CAPTURE);
        $start = null;
        $comment_blocks = [];
        foreach ($comment_matches[0] as $match) {
            if ('/*' === $match[0]) {
                $start = $match[1];
            } elseif ($start) {
                $comment_blocks[] = [$start, $match[1]];
                $start = null;
            }
        }
        return preg_replace_callback(self::ASSET_URL_PATTERN, function ($matches) use ($asset, $asset_mapper, $comment_blocks): string {
            $match_pos = $matches[0][1];
            // Ignore matches inside comments
            foreach ($comment_blocks as $block) {
                if ($match_pos > $block[0]) {
                    if ($match_pos < $block[1]) {
                        return $matches[0][0];
                    }
                    break;
                }
            }
            try {
                $resolved_source_path = Path::join(\dirname($asset->source_path), $matches[2][0]);
            } catch (RuntimeException $e) {
                $this->handle_missing_import(\sprintf('Error processing import in "%s": ', $asset->source_path) . $e->get_message(), $e);
                return $matches[0][0];
            }
            $dependent_asset = $asset_mapper->get_asset_from_source_path($resolved_source_path);
            if (null === $dependent_asset) {
                $message = \sprintf('Unable to find asset "%s" referenced in "%s". The file "%s" ', $matches[2][0], $asset->source_path, $resolved_source_path);
                if (is_file($resolved_source_path)) {
                    $message .= 'exists, but it is not in a mapped asset path. Add it to the "paths" config.';
                } else {
                    $message .= 'does not exist.';
                }
                $this->handle_missing_import($message);
                // return original, unchanged path
                return $matches[0][0];
            }
            $asset->add_dependency($dependent_asset);
            $relative_path = Path::make_relative($dependent_asset->public_path, \dirname($asset->public_path_without_digest));
            return $matches[1][0] . '"' . $relative_path . '"' . ($matches[3][0] ?? '');
        }, $content, -1, $count, \PREG_OFFSET_CAPTURE);
    }
    public function supports(Mapped_Asset $asset): bool
    {
        return 'css' === $asset->public_extension;
    }
    private function handle_missing_import(string $message, ?\Throwable $e = null): void
    {
        match ($this->missing_import_mode) {
            Asset_Compiler_Interface::MISSING_IMPORT_IGNORE => null,
            Asset_Compiler_Interface::MISSING_IMPORT_WARN => $this->logger?->warning($message),
            Asset_Compiler_Interface::MISSING_IMPORT_STRICT => throw new RuntimeException($message, 0, $e),
        };
    }
}