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
namespace Symfony\Component\Http_Kernel\Attribute;

use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Payload_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Validator\Constraint;
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Map_Uploaded_File extends Value_Resolver
{
    public Argument_Metadata $metadata;
    public function __construct(
        /** @var Constraint|array<Constraint>|null */
        public Constraint|array|null $constraints = null,
        public ?string $name = null,
        string $resolver = Request_Payload_Value_Resolver::class,
        public readonly int $validation_failed_status_code = Response::HTTP_UNPROCESSABLE_ENTITY
    )
    {
        parent::__construct($resolver);
    }
}