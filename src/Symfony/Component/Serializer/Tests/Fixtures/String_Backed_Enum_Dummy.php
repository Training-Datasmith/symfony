<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer\Tests\Fixtures;

enum StringBackedEnumDummy: string
{
    case GET = 'GET';
    case OPTIONS = 'OPTIONS';
}
