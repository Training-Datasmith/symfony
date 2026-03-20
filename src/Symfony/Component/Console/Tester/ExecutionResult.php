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
namespace Symfony\Component\Console\Tester;

use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Test_Output;
/**
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
final class Execution_Result
{
    // This is purely for memoizing purposes
    private array $results = [];
    /**
     * @param array<\Closure(string): string> $normalizers
     */
    public static function from_execution(Input_Interface $input, int $status_code, Test_Output $output, array $normalizers = []): self
    {
        return new self($input->__toString(), $status_code, $output, $normalizers);
    }
    /**
     * @param array<\Closure(string): string> $normalizers
     */
    public function __construct(public readonly string $input, public readonly int $status_code, private readonly Test_Output $output, private readonly array $normalizers)
    {
    }
    /**
     * Gets the display returned by the execution of the command or application. The display combines what was
     * written on both the output and error output.
     */
    public function get_display(bool $normalize = true): string
    {
        return $this->results['display'][$normalize] ??= $this->normalize($this->output->get_display_contents(), $normalize);
    }
    /**
     * Gets the output written to the output by the command or application.
     */
    public function get_output(bool $normalize = false): string
    {
        return $this->results['output'][$normalize] ??= $this->normalize($this->output->get_output_contents(), $normalize);
    }
    /**
     * Gets the output written to the error output by the command or application.
     */
    public function get_error_output(bool $normalize = false): string
    {
        return $this->results['errorOutput'][$normalize] ??= $this->normalize($this->output->get_error_output_contents(), $normalize);
    }
    /**
     * @return $this
     */
    public function dump(): static
    {
        $summary = "CLI: {$this->input}, Status: {$this->status_code}";
        $output = [$summary, $this->get_output(true), $this->get_error_output(true), $summary];
        \call_user_func(\function_exists('dump') ? 'dump' : 'var_dump', implode("\n\n", array_filter($output)));
        return $this;
    }
    public function dd(): never
    {
        $this->dump();
        exit(1);
    }
    private function normalize(string $value, bool $normalize): string
    {
        if (!$normalize) {
            return $value;
        }
        foreach ($this->normalizers as $normalizer) {
            $value = $normalizer($value);
        }
        return str_replace(\PHP_EOL, "\n", $value);
    }
}