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
namespace Symfony\Component\Http_Foundation\Session\Storage\Handler;

/**
 * Native session handler using PHP's built in file storage.
 *
 * @author Drak <drak@zikula.org>
 */
class Native_File_Session_Handler extends \Session_Handler
{
    /**
     * @param string|null $savePath Path of directory to save session files
     *                              Default null will leave setting as defined by PHP.
     *                              '/path', 'N;/path', or 'N;octal-mode;/path
     *
     * @see https://php.net/session.configuration#ini.session.save-path for further details.
     *
     * @throws \InvalidArgumentException On invalid $savePath
     * @throws \RuntimeException         When failing to create the save directory
     */
    public function __construct(?string $save_path = null)
    {
        $base_dir = $save_path ??= \ini_get('session.save_path');
        if ($count = substr_count($save_path, ';')) {
            if ($count > 2) {
                throw new \InvalidArgumentException(\sprintf('Invalid argument $savePath \'%s\'.', $save_path));
            }
            // characters after last ';' are the path
            $base_dir = ltrim(strrchr($save_path, ';'), ';');
        }
        if ($base_dir && !is_dir($base_dir) && !@mkdir($base_dir, 0777, true) && !is_dir($base_dir)) {
            throw new \RuntimeException(\sprintf('Session Storage was not able to create directory "%s".', $base_dir));
        }
        if ($save_path !== \ini_get('session.save_path')) {
            ini_set('session.save_path', $save_path);
        }
        if ('files' !== \ini_get('session.save_handler')) {
            ini_set('session.save_handler', 'files');
        }
    }
}