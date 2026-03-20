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

use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Payload_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Validator\Constraints\Group_Sequence;
/**
 * Controller parameter tag to map the query string of the request to typed object and validate it.
 *
 * @psalm-import-type GroupResolver from RequestPayloadValueResolver
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Map_Query_String extends Value_Resolver
{
    public Argument_Metadata $metadata;
    /**
     * @param array<string, mixed>                                             $serializationContext       The serialization context to use when deserializing the query string
     * @param string|Expression|GroupSequence|GroupResolver|array<string>|null $validationGroups           The validation groups to use when validating the query string mapping
     * @param class-string                                                     $resolver                   The class name of the resolver to use
     * @param int                                                              $validationFailedStatusCode The HTTP code to return if the validation fails
     */
    public function __construct(public readonly array $serialization_context = [], public readonly string|Expression|Group_Sequence|\Closure|array|null $validation_groups = null, string $resolver = Request_Payload_Value_Resolver::class, public readonly int $validation_failed_status_code = Response::HTTP_NOT_FOUND, public readonly ?string $key = null)
    {
        parent::__construct($resolver);
    }
}