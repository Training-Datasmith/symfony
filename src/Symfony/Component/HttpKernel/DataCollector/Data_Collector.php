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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Var_Dumper\Caster\Cut_Stub;
use Symfony\Component\Var_Dumper\Caster\Reflection_Caster;
use Symfony\Component\Var_Dumper\Cloner\Cloner_Interface;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Component\Var_Dumper\Cloner\Stub;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
/**
 * DataCollector.
 *
 * Children of this class must store the collected data in the data property.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 */
abstract class Data_Collector implements Data_Collector_Interface
{
    protected array|Data $data = [];
    private Cloner_Interface $cloner;
    /**
     * Converts the variable into a serializable Data instance.
     *
     * This array can be displayed in the template using
     * the VarDumper component.
     */
    protected function clone_var(mixed $var): Data
    {
        if ($var instanceof Data) {
            return $var;
        }
        if (!isset($this->cloner)) {
            $this->cloner = new Var_Cloner();
            $this->cloner->set_max_items(-1);
            $this->cloner->add_casters($this->get_casters());
        }
        return $this->cloner->clone_var($var);
    }
    /**
     * @return callable[] The casters to add to the cloner
     */
    protected function get_casters(): array
    {
        return ['*' => static function ($v, array $a, Stub $s, $is_nested): array {
            if (!$v instanceof Stub) {
                $b = $a;
                foreach ($a as $k => $v) {
                    if (!\is_object($v)) {
                        continue;
                    }
                    if ($v instanceof \DateTimeInterface) {
                        continue;
                    }
                    if ($v instanceof Stub) {
                        continue;
                    }
                    try {
                        $a[$k] = $s = new Cut_Stub($v);
                        if ($b[$k] === $s) {
                            // we've hit a non-typed reference
                            $a[$k] = $v;
                        }
                    } catch (\TypeError) {
                        // we've hit a typed reference
                    }
                }
            }
            return $a;
        }] + Reflection_Caster::UNSET_CLOSURE_FILE_INFO;
    }
    public function __serialize(): array
    {
        return ['data' => $this->data];
    }
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'] ?? $data["\x00*\x00data"];
    }
    public function reset(): void
    {
        $this->data = [];
    }
}