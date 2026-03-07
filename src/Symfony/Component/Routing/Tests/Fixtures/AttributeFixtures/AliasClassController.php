<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/hello', alias: ['alias', 'completely_different_name'])]
class AliasClassController
{
    #[Route('/world')]
    public function actionWorld()
    {
    }

    #[Route('/symfony')]
    public function actionSymfony()
    {
    }
}
