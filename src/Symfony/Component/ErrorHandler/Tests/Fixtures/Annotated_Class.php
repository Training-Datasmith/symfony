<?php

declare(strict_types=1);

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

class AnnotatedClass
{
    /**
     * @deprecated
     */
    public function deprecatedMethod()
    {
    }
}
