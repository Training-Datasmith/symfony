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
namespace Symfony\Component\Config\Resource;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class Reflection_Class_Resource implements Self_Checking_Resource_Interface
{
    private array $files = [];
    private readonly string $class_name;
    private string $hash;
    public function __construct(private \ReflectionClass $class_reflector, private readonly array $excluded_vendors = [])
    {
        $this->class_name = $class_reflector->name;
    }
    public function is_fresh(int $timestamp): bool
    {
        if (!isset($this->hash)) {
            $this->hash = $this->compute_hash();
            $this->load_files($this->class_reflector);
        }
        foreach ($this->files as $file => $v) {
            if (false === $filemtime = @filemtime($file)) {
                return false;
            }
            if ($filemtime > $timestamp) {
                return $this->hash === $this->compute_hash();
            }
        }
        return true;
    }
    public function __toString(): string
    {
        return 'reflection.' . $this->class_name;
    }
    public function __serialize(): array
    {
        if (!isset($this->hash)) {
            $this->hash = $this->compute_hash();
            $this->load_files($this->class_reflector);
        }
        return ['files' => $this->files, 'className' => $this->class_name, 'excludedVendors' => $this->excluded_vendors, 'hash' => $this->hash];
    }
    private function load_files(\ReflectionClass $class): void
    {
        foreach ($class->get_interfaces() as $v) {
            $this->load_files($v);
        }
        do {
            $file = $class->get_file_name();
            if (false !== $file && is_file($file)) {
                foreach ($this->excluded_vendors as $vendor) {
                    if (\in_array($file[\strlen((string) $vendor)] ?? '', ['/', \DIRECTORY_SEPARATOR], true) && str_starts_with($file, (string) $vendor)) {
                        $file = false;
                        break;
                    }
                }
                if ($file) {
                    $this->files[$file] = null;
                }
            }
            foreach ($class->get_traits() as $v) {
                $this->load_files($v);
            }
        } while ($class = $class->get_parent_class());
    }
    private function compute_hash(): string
    {
        try {
            $this->class_reflector ??= new \ReflectionClass($this->class_name);
        } catch (\Reflection_Exception) {
            // the class does not exist anymore
            return false;
        }
        $hash = hash_init('xxh128');
        foreach ($this->generate_signature($this->class_reflector) as $info) {
            hash_update($hash, (string) $info);
        }
        return hash_final($hash);
    }
    private function generate_signature(\ReflectionClass $class): iterable
    {
        $attributes = [];
        foreach ($class->get_attributes() as $a) {
            $attributes[] = [$a->get_name(), (string) $a];
        }
        yield print_r($attributes, true);
        $attributes = [];
        yield $class->get_doc_comment() ?: '';
        yield (int) $class->is_final();
        yield (int) $class->is_abstract();
        if ($class->is_trait()) {
            yield print_r(class_uses($class->name), true);
        } else {
            yield print_r(class_parents($class->name), true);
            yield print_r(class_implements($class->name), true);
            yield print_r($class->get_constants(), true);
        }
        foreach ($class->get_reflection_constants() as $constant) {
            foreach ($constant->get_attributes() as $a) {
                $attributes[] = [$a->get_name(), (string) $a];
            }
            yield $constant->name . print_r($attributes, true);
            $attributes = [];
        }
        if (!$class->is_interface()) {
            $defaults = $class->get_default_properties();
            foreach ($class->get_properties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $p) {
                foreach ($p->get_attributes() as $a) {
                    $attributes[] = [$a->get_name(), (string) $a];
                }
                yield print_r($attributes, true);
                $attributes = [];
                yield $p->get_doc_comment() ?: '';
                yield $p->is_default() ? '<default>' : '';
                yield $p->is_public() ? 'public' : 'protected';
                yield $p->is_static() ? 'static' : '';
                yield '$' . $p->name;
                yield print_r(isset($defaults[$p->name]) && !\is_object($defaults[$p->name]) ? $defaults[$p->name] : null, true);
            }
        }
        foreach ($class->get_methods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $m) {
            foreach ($this->excluded_vendors as $vendor) {
                $file = $m->get_file_name();
                if (\in_array($file[\strlen((string) $vendor)] ?? '', ['/', \DIRECTORY_SEPARATOR], true) && str_starts_with($file, (string) $vendor)) {
                    continue 2;
                }
            }
            foreach ($m->get_attributes() as $a) {
                $attributes[] = [$a->get_name(), (string) $a];
            }
            yield print_r($attributes, true);
            $attributes = [];
            $defaults = [];
            foreach ($m->get_parameters() as $p) {
                foreach ($p->get_attributes() as $a) {
                    $attributes[] = [$a->get_name(), (string) $a];
                }
                yield print_r($attributes, true);
                $attributes = [];
                if (!$p->is_default_value_available()) {
                    $defaults[$p->name] = null;
                    continue;
                }
                $defaults[$p->name] = (string) $p;
            }
            yield preg_replace('/^  @@.*/m', '', $m);
            yield print_r($defaults, true);
        }
        if ($class->is_abstract() || $class->is_interface() || $class->is_trait()) {
            return;
        }
        if (interface_exists(Event_Subscriber_Interface::class, false) && $class->is_subclass_of(Event_Subscriber_Interface::class)) {
            yield Event_Subscriber_Interface::class;
            yield print_r($class->name::get_subscribed_events(), true);
        }
        if (interface_exists(Service_Subscriber_Interface::class, false) && $class->is_subclass_of(Service_Subscriber_Interface::class)) {
            yield Service_Subscriber_Interface::class;
            yield print_r($class->name::get_subscribed_services(), true);
        }
        if (interface_exists(Form_Type_Extension_Interface::class, false) && $class->is_subclass_of(Form_Type_Extension_Interface::class)) {
            yield Form_Type_Extension_Interface::class;
            foreach ($class->name::get_extended_types() as $key => $value) {
                yield $key . print_r($value, true);
            }
        }
    }
}