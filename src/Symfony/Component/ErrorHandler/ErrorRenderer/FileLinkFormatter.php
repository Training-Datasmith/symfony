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
namespace Symfony\Component\Error_Handler\Error_Renderer;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
/**
 * Formats debug file links.
 *
 * @author Jérémy Romey <jeremy@free-agent.fr>
 *
 * @final
 */
class File_Link_Formatter
{
    private array|false $file_link_format;
    /**
     * @param string|\Closure $urlFormat The URL format, or a closure that returns it on-demand
     */
    public function __construct(string|array|null $file_link_format = null, private readonly ?Request_Stack $request_stack = null, private readonly ?string $base_dir = null, private string|\Closure|null $url_format = null)
    {
        $file_link_format ??= $_ENV['SYMFONY_IDE'] ?? $_SERVER['SYMFONY_IDE'] ?? '';
        if (!\is_array($f = $file_link_format)) {
            $f = ((Error_Renderer_Interface::IDE_LINK_FORMATS[$f] ?? $f ?: \ini_get('xdebug.file_link_format')) ?: get_cfg_var('xdebug.file_link_format')) ?: 'file://%f#L%l';
            $i = strpos((string) $f, '&', max(strrpos((string) $f, '%f'), strrpos((string) $f, '%l'))) ?: \strlen((string) $f);
            $file_link_format = [substr((string) $f, 0, $i)] + preg_split('/&([^>]++)>/', substr((string) $f, $i), -1, \PREG_SPLIT_DELIM_CAPTURE);
        }
        $this->file_link_format = $file_link_format;
    }
    public function format(string $file, int $line): string|false
    {
        if ($fmt = $this->get_file_link_format()) {
            for ($i = 1; isset($fmt[$i]); ++$i) {
                if (str_starts_with($file, (string) $k = $fmt[$i++])) {
                    $file = substr_replace($file, $fmt[$i], 0, \strlen((string) $k));
                    break;
                }
            }
            return strtr($fmt[0], ['%f' => $file, '%l' => $line]);
        }
        return false;
    }
    public function __serialize(): array
    {
        $this->file_link_format = $this->get_file_link_format();
        return ['fileLinkFormat' => $this->file_link_format];
    }
    /**
     * @internal
     */
    public static function generate_url_format(Url_Generator_Interface $router, string $route_name, string $query_string): ?string
    {
        try {
            return $router->generate($route_name) . $query_string;
        } catch (\Throwable) {
            return null;
        }
    }
    private function get_file_link_format(): array|false
    {
        if ($this->file_link_format) {
            return $this->file_link_format;
        }
        if ($this->request_stack && $this->base_dir && $this->url_format) {
            $request = $this->request_stack->get_main_request();
            if ($request instanceof Request && (!$this->url_format instanceof \Closure || $this->url_format = ($this->url_format)())) {
                return [$request->get_scheme_and_http_host() . $this->url_format, $this->base_dir . \DIRECTORY_SEPARATOR, ''];
            }
        }
        return false;
    }
}