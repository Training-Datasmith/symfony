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
namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Logstash_Formatter;
use Monolog\Handler\Abstract_Handler;
use Monolog\Handler\Formattable_Handler_Trait;
use Monolog\Handler\Processable_Handler_Trait;
use Monolog\Level;
use Monolog\Log_Record;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Contracts\Http_Client\Exception\Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * Push logs directly to Elasticsearch and format them according to Logstash specification.
 *
 * This handler dials directly with the HTTP interface of Elasticsearch. This
 * means it will slow down your application if Elasticsearch takes times to
 * answer. Even if all HTTP calls are done asynchronously.
 *
 * In a development environment, it's fine to keep the default configuration:
 * for each log, an HTTP request will be made to push the log to Elasticsearch.
 *
 * In a production environment, it's highly recommended to wrap this handler
 * in a handler with buffering capabilities (like the FingersCrossedHandler, or
 * BufferHandler) in order to call Elasticsearch only once with a bulk push. For
 * even better performance and fault tolerance, a proper ELK (https://www.elastic.co/what-is/elk-stack)
 * stack is recommended.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class Elasticsearch_Logstash_Handler extends Abstract_Handler
{
    use Formattable_Handler_Trait;
    use Processable_Handler_Trait;
    private Http_Client_Interface $client;
    /**
     * @var \SplObjectStorage<ResponseInterface, null>
     */
    private \Spl_Object_Storage $responses;
    public function __construct(private string $endpoint = 'http://127.0.0.1:9200', private string $index = 'monolog', ?Http_Client_Interface $client = null, string|int|Level $level = Level::Debug, bool $bubble = true, private string $elasticsearch_version = '1.0.0')
    {
        if (!$client && !class_exists(Http_Client::class)) {
            throw new \LogicException(\sprintf('The "%s" handler needs an HTTP client. Try running "composer require symfony/http-client".', self::class));
        }
        parent::__construct($level, $bubble);
        $this->client = $client ?: Http_Client::create(['timeout' => 1]);
        $this->responses = new \Spl_Object_Storage();
    }
    public function handle(Log_Record $record): bool
    {
        if (!$this->is_handling($record)) {
            return false;
        }
        $this->send_to_elasticsearch([$record]);
        return !$this->bubble;
    }
    public function handle_batch(array $records): void
    {
        $records = array_filter($records, $this->is_handling(...));
        if ($records) {
            $this->send_to_elasticsearch($records);
        }
    }
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Logstash_Formatter('application');
    }
    private function send_to_elasticsearch(array $records): void
    {
        $formatter = $this->get_formatter();
        if (version_compare($this->elasticsearch_version, '7', '>=')) {
            $headers = json_encode(['index' => ['_index' => $this->index]]);
        } else {
            $headers = json_encode(['index' => ['_index' => $this->index, '_type' => '_doc']]);
        }
        $body = '';
        foreach ($records as $record) {
            foreach ($this->processors as $processor) {
                $record = $processor($record);
            }
            $body .= $headers;
            $body .= "\n";
            $body .= $formatter->format($record);
            $body .= "\n";
        }
        $response = $this->client->request('POST', $this->endpoint . '/_bulk', ['body' => $body, 'headers' => ['Content-Type' => 'application/json']]);
        $this->responses[$response] = null;
        $this->wait(false);
    }
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . self::class);
    }
    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . self::class);
    }
    public function __destruct()
    {
        $this->wait(true);
    }
    private function wait(bool $blocking): void
    {
        foreach ($this->client->stream($this->responses, $blocking ? null : 0.0) as $response => $chunk) {
            try {
                if ($chunk->is_timeout() && !$blocking) {
                    continue;
                }
                if (!$chunk->is_first() && !$chunk->is_last()) {
                    continue;
                }
                if ($chunk->is_last()) {
                    unset($this->responses[$response]);
                }
            } catch (Exception_Interface $e) {
                unset($this->responses[$response]);
                error_log(\sprintf("Could not push logs to Elasticsearch:\n%s", (string) $e));
            }
        }
    }
}