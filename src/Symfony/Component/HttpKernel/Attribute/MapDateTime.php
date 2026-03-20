<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Http_Kernel\Attribute;

use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Date_Time_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
/**
 * Controller parameter tag to configure DateTime arguments.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Map_Date_Time extends Value_Resolver
{
    /**
     * @param string|null                                 $format   The DateTime format to use, @see https://php.net/datetime.format
     * @param bool                                        $disabled Whether this value resolver is disabled; this allows to enable a value resolver globally while disabling it in specific cases
     * @param class-string<ValueResolverInterface>|string $resolver The name of the resolver to use
     */
    public function __construct(public readonly ?string $format = null, bool $disabled = false, string $resolver = Date_Time_Value_Resolver::class)
    {
        parent::__construct($resolver, $disabled);
    }
}