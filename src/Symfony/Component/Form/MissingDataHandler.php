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
namespace Symfony\Component\Form;

/**
 * @internal
 */
class Missing_Data_Handler
{
    public readonly \stdClass $missing_data;
    public function __construct()
    {
        $this->missing_data = new \stdClass();
    }
    public function handle(Form_Interface $form, mixed $data): mixed
    {
        $processed_data = $this->handle_missing_data($form, $data);
        return $processed_data === $this->missing_data ? $data : $processed_data;
    }
    private function handle_missing_data(Form_Interface $form, mixed $data): mixed
    {
        $config = $form->get_config();
        $missing_data = $this->missing_data;
        $false_values = $config->get_option('false_values');
        if (\is_array($false_values)) {
            if ($data === $missing_data) {
                return $false_values[0] ?? null;
            }
            if (\in_array($data, $false_values)) {
                return $data;
            }
        }
        if (null === $data || $missing_data === $data) {
            $data = $config->get_compound() ? [] : $data;
        }
        if (\is_array($data)) {
            $children = $config->get_compound() ? $form->all() : [$form];
            foreach ($children as $child) {
                $name = $child->get_name();
                $child_data = $missing_data;
                if (\array_key_exists($name, $data)) {
                    $child_data = $data[$name];
                }
                $value = $this->handle_missing_data($child, $child_data);
                if ($missing_data !== $value) {
                    $data[$name] = $value;
                }
            }
            return $data ?: $missing_data;
        }
        return $data;
    }
}