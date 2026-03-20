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
namespace Symfony\Component\Filesystem\Exception;

/**
 * Exception class thrown when a filesystem operation failure happens.
 *
 * @author Romain Neutron <imprec@gmail.com>
 * @author Christian Gärtner <christiangaertner.film@googlemail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Io_Exception extends \RuntimeException implements Io_Exception_Interface
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, private readonly ?string $path = null)
    {
        parent::__construct($message, $code, $previous);
    }
    public function get_path(): ?string
    {
        return $this->path;
    }
}