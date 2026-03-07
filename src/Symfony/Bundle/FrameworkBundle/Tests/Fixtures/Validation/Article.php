<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

class Article implements NotExistingInterface
{
    public $category;
}
