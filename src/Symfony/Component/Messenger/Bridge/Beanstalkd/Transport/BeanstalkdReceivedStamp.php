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

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
class BeanstalkdReceivedStamp implements NonSendableStampInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $tube,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTube(): string
    {
        return $this->tube;
    }
}
