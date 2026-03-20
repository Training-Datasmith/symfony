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
namespace Symfony\Bridge\Doctrine\Types;

use Symfony\Component\Uid\Ulid;
final class Ulid_Type extends Abstract_Uid_Type
{
    public const NAME = 'ulid';
    public function get_name(): string
    {
        return self::NAME;
    }
    protected function get_uid_class(): string
    {
        return Ulid::class;
    }
}