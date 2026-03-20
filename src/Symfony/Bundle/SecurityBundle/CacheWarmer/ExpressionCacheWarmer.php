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
namespace Symfony\Bundle\Security_Bundle\Cache_Warmer;

use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
use Symfony\Component\Security\Core\Authorization\Expression_Language;
final readonly class Expression_Cache_Warmer implements Cache_Warmer_Interface
{
    /**
     * @param iterable<mixed, Expression|string> $expressions
     */
    public function __construct(private iterable $expressions, private Expression_Language $expression_language)
    {
    }
    public function is_optional(): bool
    {
        return true;
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        foreach ($this->expressions as $expression) {
            $this->expression_language->parse($expression, ['token', 'user', 'object', 'subject', 'role_names', 'request', 'trust_resolver']);
        }
        return [];
    }
}