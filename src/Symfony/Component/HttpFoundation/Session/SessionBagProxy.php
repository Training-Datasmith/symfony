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
namespace Symfony\Component\Http_Foundation\Session;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Session_Bag_Proxy implements Session_Bag_Interface
{
    private array $data;
    private ?int $usage_index = null;
    private readonly ?\Closure $usage_reporter;
    public function __construct(private Session_Bag_Interface $bag, array &$data, ?int &$usage_index, ?callable $usage_reporter)
    {
        $this->bag = $bag;
        $this->data =& $data;
        $this->usage_index =& $usage_index;
        $this->usage_reporter = null === $usage_reporter ? null : $usage_reporter(...);
    }
    public function get_bag(): Session_Bag_Interface
    {
        ++$this->usage_index;
        if ($this->usage_reporter && 0 <= $this->usage_index) {
            ($this->usage_reporter)();
        }
        return $this->bag;
    }
    public function is_empty(): bool
    {
        if (!isset($this->data[$this->bag->get_storage_key()])) {
            return true;
        }
        ++$this->usage_index;
        if ($this->usage_reporter && 0 <= $this->usage_index) {
            ($this->usage_reporter)();
        }
        return empty($this->data[$this->bag->get_storage_key()]);
    }
    public function get_name(): string
    {
        return $this->bag->get_name();
    }
    public function initialize(array &$array): void
    {
        ++$this->usage_index;
        if ($this->usage_reporter && 0 <= $this->usage_index) {
            ($this->usage_reporter)();
        }
        $this->data[$this->bag->get_storage_key()] =& $array;
        $this->bag->initialize($array);
    }
    public function get_storage_key(): string
    {
        return $this->bag->get_storage_key();
    }
    public function clear(): mixed
    {
        return $this->bag->clear();
    }
}