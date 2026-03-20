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
namespace Symfony\Component\Http_Kernel\Exception;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Controller_Does_Not_Return_Response_Exception extends \LogicException
{
    public function __construct(string $message, callable $controller, string $file, int $line)
    {
        parent::__construct($message);
        if (!$controller_definition = $this->parse_controller_definition($controller)) {
            return;
        }
        $this->file = $controller_definition['file'];
        $this->line = $controller_definition['line'];
        $r = new \ReflectionProperty(\Exception::class, 'trace');
        $r->set_value($this, array_merge([['line' => $line, 'file' => $file]], $this->get_trace()));
    }
    private function parse_controller_definition(callable $controller): ?array
    {
        if (\is_string($controller) && str_contains($controller, '::')) {
            $controller = explode('::', $controller);
        }
        if (\is_array($controller)) {
            try {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
                return ['file' => $r->get_file_name(), 'line' => $r->get_end_line()];
            } catch (\Reflection_Exception) {
                return null;
            }
        }
        if ($controller instanceof \Closure) {
            $r = new \ReflectionFunction($controller);
            return ['file' => $r->get_file_name(), 'line' => $r->get_end_line()];
        }
        if (\is_object($controller)) {
            $r = new \ReflectionClass($controller);
            try {
                $line = $r->get_method('__invoke')->get_end_line();
            } catch (\Reflection_Exception) {
                $line = $r->get_end_line();
            }
            return ['file' => $r->get_file_name(), 'line' => $line];
        }
        return null;
    }
}