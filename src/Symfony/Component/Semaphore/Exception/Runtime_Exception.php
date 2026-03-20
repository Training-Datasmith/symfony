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

namespace Symfony\Component\Semaphore\Exception;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
