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
namespace Symfony\Component\Dependency_Injection\Attribute;

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
/**
 * An attribute to tell how a dependency is used and hint named autowiring aliases.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Target
{
    /**
     * @param string|null $name The name of the target autowiring alias
     */
    public function __construct(public ?string $name = null)
    {
    }
    public function get_parsed_name(): string
    {
        if (null === $this->name) {
            throw new LogicException(\sprintf('Cannot parse the name of a #[Target] attribute that has not been resolved. Did you forget to call "%s::parseName()"?', self::class));
        }
        return lcfirst(str_replace(' ', '', ucwords((string) preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $this->name))));
    }
    public static function parse_name(\ReflectionParameter $parameter, ?self &$attribute = null, ?string &$parsed_name = null): string
    {
        $attribute = null;
        if (!$target = $parameter->get_attributes(self::class)[0] ?? null) {
            $parsed_name = (new self($parameter->name))->get_parsed_name();
            return $parameter->name;
        }
        $attribute = $target->new_instance();
        $name = $attribute->name ??= $parameter->name;
        $parsed_name = $attribute->get_parsed_name();
        if (!preg_match('/^[a-zA-Z_\x7f-\xff]/', $parsed_name)) {
            if (($function = $parameter->get_declaring_function()) instanceof \ReflectionMethod) {
                $function = $function->class . '::' . $function->name;
            } else {
                $function = $function->name;
            }
            throw new InvalidArgumentException(\sprintf('Invalid #[Target] name "%s" on parameter "$%s" of "%s()": the first character must be a letter.', $name, $parameter->name, $function));
        }
        return preg_match('/^[a-zA-Z0-9_\x7f-\xff]++$/', $name) ? $name : $parsed_name;
    }
}