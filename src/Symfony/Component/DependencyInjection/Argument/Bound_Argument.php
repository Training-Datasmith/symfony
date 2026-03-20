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
namespace Symfony\Component\Dependency_Injection\Argument;

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
final class Bound_Argument implements Argument_Interface
{
    use Argument_Trait;
    public const SERVICE_BINDING = 0;
    public const DEFAULTS_BINDING = 1;
    public const INSTANCEOF_BINDING = 2;
    private static int $sequence = 0;
    private ?int $identifier = null;
    private ?bool $used = null;
    public function __construct(private mixed $value, bool $track_usage = true, private int $type = 0, private ?string $file = null)
    {
        if ($track_usage) {
            $this->identifier = ++self::$sequence;
        } else {
            $this->used = true;
        }
    }
    public function get_values(): array
    {
        return [$this->value, $this->identifier, $this->used, $this->type, $this->file];
    }
    public function set_values(array $values): void
    {
        if (5 === \count($values)) {
            [$this->value, $this->identifier, $this->used, $this->type, $this->file] = $values;
        } else {
            [$this->value, $this->identifier, $this->used] = $values;
        }
    }
}