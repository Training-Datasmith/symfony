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
namespace Symfony\Bundle\Framework_Bundle\Error_Handler\Error_Renderer;

use Symfony\Component\Error_Handler\Error_Renderer\Error_Renderer_Interface;
/**
 * @internal
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
final class Runtime_Mode_Error_Renderer_Selector
{
    /**
     * @param \Closure(): ErrorRendererInterface $htmlErrorRenderer
     * @param \Closure(): ErrorRendererInterface $cliErrorRenderer
     */
    public static function select(bool $is_web_mode, \Closure $html_error_renderer, \Closure $cli_error_renderer): Error_Renderer_Interface
    {
        return ($is_web_mode ? $html_error_renderer : $cli_error_renderer)();
    }
}