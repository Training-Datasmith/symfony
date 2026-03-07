<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\PartialInput;

class FinalInput
{
    public string $uuid;
    public string $name;
    public ?string $email = null;
    public ?string $website = null;
}
