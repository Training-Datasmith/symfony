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
namespace Symfony\Component\Error_Handler;

use Symfony\Component\Error_Handler\Exception\Silenced_Error_Context;
/**
 * @internal
 */
class Throwable_Utils
{
    public static function get_severity(Silenced_Error_Context|\Throwable $throwable): int
    {
        if ($throwable instanceof \ErrorException || $throwable instanceof Silenced_Error_Context) {
            return $throwable->get_severity();
        }
        if ($throwable instanceof \ParseError) {
            return \E_PARSE;
        }
        if ($throwable instanceof \TypeError) {
            return \E_RECOVERABLE_ERROR;
        }
        return \E_ERROR;
    }
}