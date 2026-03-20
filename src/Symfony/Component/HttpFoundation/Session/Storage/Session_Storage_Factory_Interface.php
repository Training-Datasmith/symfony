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
namespace Symfony\Component\Http_Foundation\Session\Storage;

use Symfony\Component\Http_Foundation\Request;
/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface Session_Storage_Factory_Interface
{
    /**
     * Creates a new instance of SessionStorageInterface.
     */
    public function create_storage(?Request $request): Session_Storage_Interface;
}