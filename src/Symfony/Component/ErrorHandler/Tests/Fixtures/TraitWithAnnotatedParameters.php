<?php

declare(strict_types=1);

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

trait TraitWithAnnotatedParameters
{
    /**
     * `@param` annotations in traits are not parsed.
     */
    public function isSymfony()
    {
    }
}
