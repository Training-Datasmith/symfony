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
namespace Symfony\Component\Asset_Mapper\Compressor;

use Symfony\Component\Process\Executable_Finder;
use Symfony\Component\Process\Process;
/**
 * @internal
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
trait Compressor_Trait
{
    private ?\Closure $method = null;
    private ?string $executable = null;
    /**
     * @var ?resource
     */
    private $stream_context;
    private ?string $unsupported_reason = null;
    private function initialize(): void
    {
        if ('' !== self::WRAPPER && \in_array(self::WRAPPER, stream_get_wrappers(), true)) {
            $this->method = $this->compress_with_extension(...);
            return;
        }
        if (!class_exists(Process::class)) {
            if ('' === self::WRAPPER) {
                $this->unsupported_reason = \sprintf('%s compression is unsupported. Run "composer require symfony/process" and install the "%s" command.', self::COMMAND, self::COMMAND);
            } else {
                $this->unsupported_reason = \sprintf('%s compression is unsupported. Install the "%s" extension or run "composer require symfony/process" and install the "%s" command.', self::COMMAND, self::PHP_EXTENSION, self::COMMAND);
            }
            return;
        }
        if (null === $this->executable) {
            $executable_finder = new Executable_Finder();
            $this->executable = $executable_finder->find(self::COMMAND);
            if (null === $this->executable) {
                if (self::WRAPPER === '') {
                    $this->unsupported_reason = \sprintf('%s compression is unsupported. Install the "%s" command.', self::COMMAND, self::COMMAND);
                } else {
                    $this->unsupported_reason = \sprintf('%s compression is unsupported. Install the "%s" extension or the "%s" command.', self::COMMAND, self::PHP_EXTENSION, self::COMMAND);
                }
                return;
            }
        }
        $this->method = $this->compress_with_binary(...);
    }
    public function compress(string $path): void
    {
        if (null === $this->method && null === $this->unsupported_reason) {
            $this->initialize();
        }
        if (null !== $this->unsupported_reason) {
            throw new \RuntimeException($this->unsupported_reason);
        }
        ($this->method)($path);
    }
    public function get_unsupported_reason(): ?string
    {
        if (null !== $this->method) {
            return null;
        }
        $this->initialize();
        return $this->unsupported_reason;
    }
    abstract private function compress_with_binary(string $path): void;
    /**
     * @return resource
     */
    abstract private function create_stream_context();
    private function compress_with_extension(string $path): void
    {
        if (null === $this->stream_context) {
            $this->stream_context = $this->create_stream_context();
        }
        if (!copy($path, \sprintf('%s://%s.%s', self::WRAPPER, $path, self::FILE_EXTENSION), $this->stream_context)) {
            throw new \RuntimeException(\sprintf('The compressed file "%s.%s" could not be written.', $path, self::FILE_EXTENSION));
        }
    }
}