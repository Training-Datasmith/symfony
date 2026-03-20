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
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Method_Not_Allowed_Http_Exception extends Http_Exception
{
    /**
     * @param string[] $allow An array of allowed methods
     */
    public function __construct(array $allow, string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        $headers['Allow'] = strtoupper(implode(', ', $allow));
        parent::__construct(405, $message, $previous, $headers, $code);
    }
}