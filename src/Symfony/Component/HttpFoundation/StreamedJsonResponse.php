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
namespace Symfony\Component\Http_Foundation;

/**
 * StreamedJsonResponse represents a streamed HTTP response for JSON.
 *
 * A StreamedJsonResponse uses a structure and generics to create an
 * efficient resource-saving JSON response.
 *
 * It is recommended to use flush() function after a specific number of items to directly stream the data.
 *
 * @see flush()
 *
 * @author Alexander Schranz <alexander@sulu.io>
 *
 * Example usage:
 *
 *     function loadArticles(): \Generator
 *         // some streamed loading
 *         yield ['title' => 'Article 1'];
 *         yield ['title' => 'Article 2'];
 *         yield ['title' => 'Article 3'];
 *         // recommended to use flush() after every specific number of items
 *     }),
 *
 *     $response = new StreamedJsonResponse(
 *         // json structure with generators in which will be streamed
 *         [
 *             '_embedded' => [
 *                 'articles' => loadArticles(), // any generator which you want to stream as list of data
 *             ],
 *         ],
 *     );
 */
class Streamed_Json_Response extends Streamed_Response
{
    private const PLACEHOLDER = '__symfony_json__';
    /**
     * @param mixed[]                        $data            JSON Data containing PHP generators which will be streamed as list of data or a Generator
     * @param int                            $status          The HTTP status code (200 "OK" by default)
     * @param array<string, string|string[]> $headers         An array of HTTP headers
     * @param int                            $encodingOptions Flags for the json_encode() function
     */
    public function __construct(private readonly iterable $data, int $status = 200, array $headers = [], private readonly int $encoding_options = Json_Response::DEFAULT_ENCODING_OPTIONS)
    {
        parent::__construct($this->stream(...), $status, $headers);
        if (!$this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }
    private function stream(): void
    {
        $json_encoding_options = \JSON_THROW_ON_ERROR | $this->encoding_options;
        $key_encoding_options = $json_encoding_options & ~\JSON_NUMERIC_CHECK;
        $this->stream_data($this->data, $json_encoding_options, $key_encoding_options);
    }
    private function stream_data(mixed $data, int $json_encoding_options, int $key_encoding_options): void
    {
        if (\is_array($data)) {
            $this->stream_array($data, $json_encoding_options, $key_encoding_options);
            return;
        }
        if (is_iterable($data) && !$data instanceof \JsonSerializable) {
            $this->stream_iterable($data, $json_encoding_options, $key_encoding_options);
            return;
        }
        echo json_encode($data, $json_encoding_options);
    }
    private function stream_array(array $data, int $json_encoding_options, int $key_encoding_options): void
    {
        $generators = [];
        array_walk_recursive($data, static function (&$item, $key) use (&$generators): void {
            if (self::PLACEHOLDER === $key) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $key;
            }
            // generators should be used but for better DX all kind of Traversable and objects are supported
            if (\is_object($item)) {
                $generators[] = $item;
                $item = self::PLACEHOLDER;
            } elseif (self::PLACEHOLDER === $item) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $item;
            }
        });
        $json_parts = explode('"' . self::PLACEHOLDER . '"', json_encode($data, $json_encoding_options));
        foreach ($generators as $index => $generator) {
            // send first and between parts of the structure
            echo $json_parts[$index];
            $this->stream_data($generator, $json_encoding_options, $key_encoding_options);
        }
        // send last part of the structure
        echo $json_parts[array_key_last($json_parts)];
    }
    private function stream_iterable(iterable $iterable, int $json_encoding_options, int $key_encoding_options): void
    {
        $is_first_item = true;
        $start_tag = '[';
        foreach ($iterable as $key => $item) {
            if ($is_first_item) {
                $is_first_item = false;
                // depending on the first elements key the generator is detected as a list or map
                // we can not check for a whole list or map because that would hurt the performance
                // of the streamed response which is the main goal of this response class
                if (0 !== $key) {
                    $start_tag = '{';
                }
                echo $start_tag;
            } else {
                // if not first element of the generic, a separator is required between the elements
                echo ',';
            }
            if ('{' === $start_tag) {
                echo json_encode((string) $key, $key_encoding_options) . ':';
            }
            $this->stream_data($item, $json_encoding_options, $key_encoding_options);
        }
        if ($is_first_item) {
            // indicates that the generator was empty
            echo '[';
        }
        echo '[' === $start_tag ? ']' : '}';
    }
}