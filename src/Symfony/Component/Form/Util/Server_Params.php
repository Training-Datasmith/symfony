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
namespace Symfony\Component\Form\Util;

use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Server_Params
{
    public function __construct(private readonly ?Request_Stack $request_stack = null)
    {
    }
    /**
     * Returns true if the POST max size has been exceeded in the request.
     */
    public function has_post_max_size_been_exceeded(): bool
    {
        $content_length = $this->get_content_length();
        $max_content_length = $this->get_post_max_size();
        return $max_content_length && $content_length > $max_content_length;
    }
    /**
     * Returns maximum post size in bytes.
     */
    public function get_post_max_size(): int|float|null
    {
        $ini_max = strtolower($this->get_normalized_ini_post_max_size());
        if ('' === $ini_max) {
            return null;
        }
        $max = ltrim($ini_max, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }
        switch (substr($ini_max, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }
        return $max;
    }
    /**
     * Returns the normalized "post_max_size" ini setting.
     */
    public function get_normalized_ini_post_max_size(): string
    {
        return strtoupper(trim(\ini_get('post_max_size')));
    }
    /**
     * Returns the content length of the request.
     */
    public function get_content_length(): mixed
    {
        if (null !== $this->request_stack && null !== $request = $this->request_stack->get_current_request()) {
            return $request->server->get('CONTENT_LENGTH');
        }
        return isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : null;
    }
}