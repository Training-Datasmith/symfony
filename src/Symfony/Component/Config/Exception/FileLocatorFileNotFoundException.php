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
namespace Symfony\Component\Config\Exception;

/**
 * File locator exception if a file does not exist.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class File_Locator_File_Not_Found_Exception extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, private readonly array $paths = [])
    {
        parent::__construct($message, $code, $previous);
    }
    public function get_paths(): array
    {
        return $this->paths;
    }
}