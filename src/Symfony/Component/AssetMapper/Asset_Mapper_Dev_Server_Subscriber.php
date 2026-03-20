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
namespace Symfony\Component\Asset_Mapper;

use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Binary_File_Response;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
/**
 * Functions like a controller that returns assets from the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class Asset_Mapper_Dev_Server_Subscriber implements Event_Subscriber_Interface
{
    // source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    private const EXTENSIONS_MAP = ['aac' => 'audio/aac', 'abw' => 'application/x-abiword', 'arc' => 'application/x-freearc', 'avif' => 'image/avif', 'avi' => 'video/x-msvideo', 'azw' => 'application/vnd.amazon.ebook', 'bin' => 'application/octet-stream', 'bmp' => 'image/bmp', 'bz' => 'application/x-bzip', 'bz2' => 'application/x-bzip2', 'cda' => 'application/x-cdf', 'csh' => 'application/x-csh', 'css' => 'text/css', 'csv' => 'text/csv', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'eot' => 'application/vnd.ms-fontobject', 'epub' => 'application/epub+zip', 'gz' => 'application/gzip', 'gif' => 'image/gif', 'htm' => 'text/html', 'html' => 'text/html', 'ico' => 'image/vnd.microsoft.icon', 'ics' => 'text/calendar', 'jar' => 'application/java-archive', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'js' => 'text/javascript', 'json' => 'application/json', 'jsonld' => 'application/ld+json', 'mid' => 'audio/midi', 'midi' => 'audio/midi', 'mjs' => 'text/javascript', 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'mpeg' => 'video/mpeg', 'mpkg' => 'application/vnd.apple.installer+xml', 'odp' => 'application/vnd.oasis.opendocument.presentation', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'odt' => 'application/vnd.oasis.opendocument.text', 'oga' => 'audio/ogg', 'ogv' => 'video/ogg', 'ogx' => 'application/ogg', 'opus' => 'audio/opus', 'otf' => 'font/otf', 'png' => 'image/png', 'pdf' => 'application/pdf', 'php' => 'application/x-httpd-php', 'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'rar' => 'application/vnd.rar', 'rtf' => 'application/rtf', 'sh' => 'application/x-sh', 'svg' => 'image/svg+xml', 'tar' => 'application/x-tar', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'ts' => 'video/mp2t', 'ttf' => 'font/ttf', 'txt' => 'text/plain', 'vsd' => 'application/vnd.visio', 'wav' => 'audio/wav', 'weba' => 'audio/webm', 'webm' => 'video/webm', 'webp' => 'image/webp', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
    private readonly string $public_prefix;
    /**
     * @var array<string, string>
     */
    private array $extensions_map;
    /**
     * @param array<string, string> $extensionsMap
     */
    public function __construct(private readonly Asset_Mapper_Interface $asset_mapper, string $public_prefix = '/assets/', array $extensions_map = [], private readonly ?Cache_Item_Pool_Interface $cache_map_cache = null, private readonly ?Profiler $profiler = null)
    {
        $this->public_prefix = '/' . trim($public_prefix, '/') . '/';
        $this->extensions_map = array_merge(self::EXTENSIONS_MAP, $extensions_map);
    }
    public function on_kernel_request(Request_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        $path_info = rawurldecode($event->get_request()->get_path_info());
        if (!str_starts_with($path_info, $this->public_prefix)) {
            return;
        }
        $asset = $this->find_asset_from_cache($path_info);
        if (!$asset) {
            throw new Not_Found_Http_Exception(\sprintf('Asset with public path "%s" not found.', $path_info));
        }
        $this->profiler?->disable();
        if (null !== $asset->content) {
            $response = new Response($asset->content);
        } else {
            $response = new Binary_File_Response($asset->source_path, autoLastModified: false);
        }
        $response->set_public()->set_max_age(604800)->set_immutable()->set_etag($asset->digest);
        if ($media_type = $this->get_media_type($asset->public_path)) {
            $response->headers->set('Content-Type', $media_type);
        }
        $response->headers->set('X-Assets-Dev', '1');
        $event->set_response($response);
        $event->stop_propagation();
    }
    public function on_kernel_response(Response_Event $event): void
    {
        if ($event->get_response()->headers->get('X-Assets-Dev')) {
            $event->stop_propagation();
        }
    }
    public static function get_subscribed_events(): array
    {
        return [
            // priority higher than RouterListener
            Kernel_Events::REQUEST => [['onKernelRequest', 35]],
            // Highest priority possible to bypass all other listeners
            Kernel_Events::RESPONSE => [['onKernelResponse', 2048]],
        ];
    }
    private function get_media_type(string $path): ?string
    {
        $extension = pathinfo($path, \PATHINFO_EXTENSION);
        return $this->extensions_map[$extension] ?? null;
    }
    private function find_asset_from_cache(string $path_info): ?Mapped_Asset
    {
        $cached_asset = null;
        if (null !== $this->cache_map_cache) {
            $cached_asset = $this->cache_map_cache->get_item(hash('xxh128', $path_info));
            $asset = $cached_asset->is_hit() ? $this->asset_mapper->get_asset($cached_asset->get()) : null;
            if (null !== $asset && $asset->public_path === $path_info) {
                return $asset;
            }
        }
        // we did not find a match
        $asset = null;
        foreach ($this->asset_mapper->all_assets() as $asset_candidate) {
            if ($path_info === $asset_candidate->public_path) {
                $asset = $asset_candidate;
                break;
            }
        }
        if (null === $asset) {
            return null;
        }
        if (null !== $cached_asset) {
            $cached_asset->set($asset->logical_path);
            $this->cache_map_cache->save($cached_asset);
        }
        return $asset;
    }
}