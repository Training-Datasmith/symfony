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
namespace Symfony\Component\Dotenv\Exception;

/**
 * Thrown when a file has a syntax error.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Format_Exception extends \LogicException implements Exception_Interface
{
    public function __construct(string $message, private readonly Format_Exception_Context $context, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf("%s in \"%s\" at line %d.\n%s", $message, $context->get_path(), $context->get_lineno(), $context->get_details()), $code, $previous);
    }
    public function get_context(): Format_Exception_Context
    {
        return $this->context;
    }
}