<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

class ActionPathController
{
    #[Route('/path', name: 'action')]
    public function action()
    {
    }
}
