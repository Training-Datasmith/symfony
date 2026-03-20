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
namespace Symfony\Component\Console\Exception;

/**
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception_Interface
{
    /**
     * @internal
     */
    public static function from_enum_value(string $name, string $value, array|\Closure $suggested_values): self
    {
        $error = \sprintf('The value "%s" is not valid for the "%s" argument.', $value, $name);
        if (\is_array($suggested_values)) {
            $error .= \sprintf(' Supported values are "%s".', implode('", "', $suggested_values));
        }
        return new self($error);
    }
}