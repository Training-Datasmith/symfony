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

use Monolog\Log_Record;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Token_Interface;
/**
 * The base class for security token processors.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 *
 * @internal
 */
abstract class Abstract_Token_Processor
{
    public function __construct(protected Token_Storage_Interface $token_storage)
    {
    }
    abstract protected function get_key(): string;
    abstract protected function get_token(): ?Token_Interface;
    public function __invoke(Log_Record $record): Log_Record
    {
        $record->extra[$this->get_key()] = null;
        if (null !== $token = $this->get_token()) {
            $record->extra[$this->get_key()] = ['authenticated' => (bool) $token->get_user(), 'roles' => $token->get_role_names()];
            $record->extra[$this->get_key()]['user_identifier'] = $token->get_user_identifier();
        }
        return $record;
    }
}