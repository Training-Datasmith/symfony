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
namespace Symfony\Component\Http_Client\Chunk;

use Symfony\Component\Http_Client\Exception\Json_Exception;
use Symfony\Contracts\Http_Client\Chunk_Interface;
/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Server_Sent_Event extends Data_Chunk implements Chunk_Interface
{
    private string $data = '';
    private string $id = '';
    private string $type = 'message';
    private float $retry = 0;
    private ?array $json_data = null;
    public function __construct(string $content)
    {
        parent::__construct(-1, $content);
        // remove BOM
        if (str_starts_with($content, "﻿")) {
            $content = substr($content, 3);
        }
        foreach (preg_split("/(?:\r\n|[\r\n])/", $content) as $line) {
            if (0 === $i = strpos($line, ':')) {
                continue;
            }
            $i = false === $i ? \strlen($line) : $i;
            $field = substr($line, 0, $i);
            $i += 1 + (' ' === ($line[1 + $i] ?? ''));
            switch ($field) {
                case 'id':
                    $this->id = substr($line, $i);
                    break;
                case 'event':
                    $this->type = substr($line, $i);
                    break;
                case 'data':
                    $this->data .= ('' === $this->data ? '' : "\n") . substr($line, $i);
                    break;
                case 'retry':
                    $retry = substr($line, $i);
                    if ('' !== $retry && \strlen($retry) === strspn($retry, '0123456789')) {
                        $this->retry = $retry / 1000.0;
                    }
                    break;
            }
        }
    }
    public function get_id(): string
    {
        return $this->id;
    }
    public function get_type(): string
    {
        return $this->type;
    }
    public function get_data(): string
    {
        return $this->data;
    }
    public function get_retry(): float
    {
        return $this->retry;
    }
    /**
     * Gets the SSE data decoded as an array when it's a JSON payload.
     */
    public function get_array_data(): array
    {
        if (null !== $this->json_data) {
            return $this->json_data;
        }
        if ('' === $this->data) {
            throw new Json_Exception(\sprintf('Server-Sent Event%s data is empty.', '' !== $this->id ? \sprintf(' "%s"', $this->id) : ''));
        }
        try {
            $json_data = json_decode($this->data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\Json_Exception $e) {
            throw new Json_Exception(\sprintf('Decoding Server-Sent Event%s failed: ', '' !== $this->id ? \sprintf(' "%s"', $this->id) : '') . $e->get_message(), $e->get_code());
        }
        if (!\is_array($json_data)) {
            throw new Json_Exception(\sprintf('JSON content was expected to decode to an array, "%s" returned in Server-Sent Event%s.', get_debug_type($json_data), '' !== $this->id ? \sprintf(' "%s"', $this->id) : ''));
        }
        return $this->json_data = $json_data;
    }
}