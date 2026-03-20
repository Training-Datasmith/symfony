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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Contracts\Service\Attribute\Required;
/**
 * Looks for definitions with autowiring enabled and registers their corresponding "#[Required]" methods as setters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Autowire_Required_Methods_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        $value = parent::process_value($value, $is_root);
        if (!$value instanceof Definition || !$value->is_autowired() || $value->is_abstract() || !$value->get_class()) {
            return $value;
        }
        if (!$reflection_class = $this->container->get_reflection_class($value->get_class(), false)) {
            return $value;
        }
        $already_called_methods = [];
        $withers = [];
        foreach ($value->get_method_calls() as [$method]) {
            $already_called_methods[strtolower((string) $method)] = true;
        }
        foreach ($reflection_class->get_methods() as $reflection_method) {
            $r = $reflection_method;
            if ($r->is_constructor()) {
                continue;
            }
            if (isset($already_called_methods[strtolower($r->name)])) {
                continue;
            }
            while (true) {
                if ($r->get_attributes(Required::class)) {
                    if ($this->is_wither($r, $r->get_doc_comment() ?: '')) {
                        $withers[] = [$r->name, [], true];
                    } else {
                        $value->add_method_call($r->name, []);
                    }
                    break;
                }
                if (!$r->has_prototype()) {
                    break;
                }
                $r = $r->get_prototype();
            }
        }
        if ($withers) {
            // Prepend withers to prevent creating circular loops
            $setters = $value->get_method_calls();
            $value->set_method_calls($withers);
            foreach ($setters as $call) {
                $value->add_method_call($call[0], $call[1], $call[2] ?? false);
            }
        }
        return $value;
    }
    private function is_wither(\ReflectionMethod $reflection_method, string $doc): bool
    {
        $match = preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@return\s++(static|\$this)[\s\*]#i', $doc, $matches);
        if ($match && 'static' === $matches[1]) {
            return true;
        }
        if ($match && '$this' === $matches[1]) {
            return false;
        }
        $reflection_type = $reflection_method->has_return_type() ? $reflection_method->get_return_type() : null;
        return $reflection_type instanceof \ReflectionNamedType && 'static' === $reflection_type->get_name();
    }
}