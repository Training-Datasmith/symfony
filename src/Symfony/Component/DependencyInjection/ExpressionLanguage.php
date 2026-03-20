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
namespace Symfony\Component\Dependency_Injection;

use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Component\Expression_Language\Expression_Language as BaseExpressionLanguage;
if (!class_exists(Base_Expression_Language::class)) {
    return;
}
/**
 * Adds some function to the default ExpressionLanguage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see ExpressionLanguageProvider
 */
class Expression_Language extends Base_Expression_Language
{
    public function __construct(?Cache_Item_Pool_Interface $cache = null, iterable $providers = [], ?callable $service_compiler = null, ?\Closure $get_env = null)
    {
        if (!\is_array($providers)) {
            $providers = iterator_to_array($providers, false);
        }
        // prepend the default provider to let users override it easily
        array_unshift($providers, new Expression_Language_Provider($service_compiler, $get_env));
        parent::__construct($cache, $providers);
    }
}