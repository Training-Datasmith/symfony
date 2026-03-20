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

use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Exception_Data_Collector extends Data_Collector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (null !== $exception) {
            $this->data = ['exception' => Flatten_Exception::create_with_data_representation($exception)];
        }
    }
    public function has_exception(): bool
    {
        return isset($this->data['exception']);
    }
    public function get_exception(): \Exception|Flatten_Exception
    {
        return $this->data['exception'];
    }
    public function get_message(): string
    {
        return $this->data['exception']->get_message();
    }
    public function get_code(): int|string
    {
        return $this->data['exception']->get_code();
    }
    public function get_status_code(): int
    {
        return $this->data['exception']->get_status_code();
    }
    public function get_trace(): array
    {
        return $this->data['exception']->get_trace();
    }
    public function get_name(): string
    {
        return 'exception';
    }
}