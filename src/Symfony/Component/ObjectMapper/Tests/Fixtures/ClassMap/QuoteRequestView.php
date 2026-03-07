<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Quote::class)]
final class QuoteRequestView
{
    public string $id;

    public CostRequestView $cost;
}
