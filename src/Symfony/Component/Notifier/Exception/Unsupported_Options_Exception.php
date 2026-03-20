<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Exception;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Raphaël Geffroy <raphael@geffroy.dev>
 */
class UnsupportedOptionsException extends LogicException
{
    public function __construct(string $transport, string $supported, MessageOptionsInterface $given)
    {
        parent::__construct(\sprintf('The "%s" transport only supports instances of "%s" for options (instance of "%s" given).', $transport, $supported, get_debug_type($given)));
    }
}
