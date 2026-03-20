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
namespace Symfony\Bridge\Monolog\Processor;

use Symfony\Component\Security\Core\Authentication\Token\Token_Interface;
/**
 * Adds the current security token to the log entry.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 */
final class Token_Processor extends Abstract_Token_Processor
{
    protected function get_key(): string
    {
        return 'token';
    }
    protected function get_token(): ?Token_Interface
    {
        return $this->token_storage->get_token();
    }
}