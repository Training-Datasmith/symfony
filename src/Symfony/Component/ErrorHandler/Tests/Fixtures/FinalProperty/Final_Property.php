<?php

declare(strict_types=1);

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalProperty;

class FinalProperty
{
    /**
     * @final
     */
    public $pub;

    /**
     * @final
     */
    protected $prot;

    /**
     * @final
     */
    private $priv;

    /**
     * @final
     */
    public $notOverriden;

    protected $implicitlyFinal;

    protected string $typedSoNotFinal;

    protected $deprecated;
}
