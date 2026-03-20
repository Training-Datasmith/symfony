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
namespace Symfony\Bridge\Php_Unit;

use Php_Unit\Framework\Test;
use Php_Unit\Framework\Test_Case;
use Php_Unit\Framework\Test_Listener;
use Php_Unit\Framework\Test_Listener_Default_Implementation;
use Php_Unit\Framework\Warning;
use Php_Unit\Util\Annotation\Registry;
use Php_Unit\Util\Test as TestUtil;
class Coverage_Listener implements Test_Listener
{
    use Test_Listener_Default_Implementation;
    private $sut_fqcn_resolver;
    public function __construct(?callable $sut_fqcn_resolver = null, private bool $warning_on_sut_not_found = false)
    {
        $this->sut_fqcn_resolver = $sut_fqcn_resolver ?? static function (Test $test): ?string {
            $class = $test::class;
            $sut_fqcn = str_replace('\Tests\\', '\\', $class);
            $sut_fqcn = preg_replace('{Test$}', '', $sut_fqcn);
            return class_exists($sut_fqcn) ? $sut_fqcn : null;
        };
    }
    public function start_test(Test $test): void
    {
        if (!$test instanceof Test_Case) {
            return;
        }
        $annotations = Test_Util::parse_test_method_annotations($test::class, $test->get_name(false));
        $ignored_annotations = ['covers', 'coversDefaultClass', 'coversNothing'];
        foreach ($ignored_annotations as $annotation) {
            if (isset($annotations['class'][$annotation]) || isset($annotations['method'][$annotation])) {
                return;
            }
        }
        $sut_fqcn = ($this->sut_fqcn_resolver)($test);
        if (!$sut_fqcn) {
            if ($this->warning_on_sut_not_found) {
                $test->get_test_result_object()->add_warning($test, new Warning('Could not find the tested class.'), 0);
            }
            return;
        }
        $covers = $sut_fqcn;
        if (!\is_array($sut_fqcn)) {
            $covers = [$sut_fqcn];
            while ($parent = get_parent_class($sut_fqcn)) {
                $covers[] = $parent;
                $sut_fqcn = $parent;
            }
        }
        if (class_exists(Registry::class)) {
            $this->add_covers_for_doc_block_inside_registry($test, $covers);
            return;
        }
        $this->add_covers_for_class_to_annotation_cache($test, $covers);
    }
    private function add_covers_for_class_to_annotation_cache(Test $test, array $covers): void
    {
        $r = new \ReflectionProperty(Test_Util::class, 'annotationCache');
        $cache = $r->get_value();
        $cache = array_replace_recursive($cache, [$test::class => ['covers' => $covers]]);
        $r->set_value(Test_Util::class, $cache);
    }
    private function add_covers_for_doc_block_inside_registry(Test $test, array $covers): void
    {
        $doc_block = Registry::get_instance()->for_class_name($test::class);
        $symbol_annotations = new \ReflectionProperty($doc_block, 'symbolAnnotations');
        // Exclude internal classes; PHPUnit 9.1+ is picky about tests covering, say, a \RuntimeException
        $covers = array_filter($covers, static function (string $class): bool {
            $reflector = new \ReflectionClass($class);
            return $reflector->is_user_defined();
        });
        $symbol_annotations->set_value($doc_block, array_replace($doc_block->symbol_annotations(), ['covers' => $covers]));
    }
}