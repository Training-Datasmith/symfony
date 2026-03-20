<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

// Missing "use" for Assert\Type is on purpose

class SubCategory extends Category
{
    #[Assert\Type('string')]
    public $main;
}
