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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
/**
 * Sets the session in the request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Session_Listener extends Abstract_Session_Listener
{
    public function __construct(private readonly ?Container_Interface $container = null, bool $debug = false, array $session_options = [])
    {
        parent::__construct($container, $debug, $session_options);
    }
    protected function get_session(): ?Session_Interface
    {
        if ($this->container->has('session_factory')) {
            return $this->container->get('session_factory')->create_session();
        }
        return null;
    }
}