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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final readonly class Csrf_Runtime
{
    public function __construct(private Csrf_Token_Manager_Interface $csrf_token_manager)
    {
    }
    public function get_csrf_token(string $token_id): string
    {
        return $this->csrf_token_manager->get_token($token_id)->get_value();
    }
}