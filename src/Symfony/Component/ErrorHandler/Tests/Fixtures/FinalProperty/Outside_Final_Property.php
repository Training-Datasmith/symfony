<?php

declare(strict_types=1);

namespace Test\Symfony\Component\ErrorHandler\Tests\FinalProperty;

class OutsideFinalProperty
{
    /**
     * @final
     */
    public $final;

    protected $notImplicitlyFinalBecauseNotInSymfony;

    /**
     * @final
     */
    public $notOverriden;

    /**
     * @final
     */
    protected $deprecated;
}
