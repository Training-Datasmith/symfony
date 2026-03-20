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

use Symfony\Component\Security\Core\Authentication\Token\Switch_User_Token;
use Symfony\Component\Security\Core\Authentication\Token\Token_Interface;
/**
 * Adds the original security token to the log entry.
 *
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 */
final class Switch_User_Token_Processor extends Abstract_Token_Processor
{
    protected function get_key(): string
    {
        return 'impersonator_token';
    }
    protected function get_token(): ?Token_Interface
    {
        $token = $this->token_storage->get_token();
        if ($token instanceof Switch_User_Token) {
            return $token->get_original_token();
        }
        return null;
    }
}