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
namespace Symfony\Component\Http_Foundation\Session;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface Session_Factory_Interface
{
    public function create_session(): Session_Interface;
}