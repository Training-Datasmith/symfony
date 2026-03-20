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
namespace Symfony\Component\Http_Kernel\Exception;

/**
 * Interface for HTTP error exceptions.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface Http_Exception_Interface extends \Throwable
{
    /**
     * Returns the status code.
     */
    public function get_status_code(): int;
    /**
     * Returns response headers.
     */
    public function get_headers(): array;
}