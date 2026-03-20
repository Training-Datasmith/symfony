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

use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Util\Form_Util;
use Symfony\Component\Form\Util\Server_Params;
/**
 * A request handler using PHP super globals $_GET, $_POST and $_SERVER.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Native_Request_Handler implements Request_Handler_Interface
{
    private readonly Server_Params $server_params;
    private readonly Missing_Data_Handler $missing_data_handler;
    /**
     * The allowed keys of the $_FILES array.
     */
    private const FILE_KEYS = ['error', 'full_path', 'name', 'size', 'tmp_name', 'type'];
    public function __construct(?Server_Params $params = null)
    {
        $this->server_params = $params ?? new Server_Params();
        $this->missing_data_handler = new Missing_Data_Handler();
    }
    /**
     * @throws UnexpectedTypeException If the $request is not null
     */
    public function handle_request(Form_Interface $form, mixed $request = null): void
    {
        if (null !== $request) {
            throw new Unexpected_Type_Exception($request, 'null');
        }
        $name = $form->get_name();
        $method = $form->get_config()->get_method();
        $missing_data = $this->missing_data_handler->missing_data;
        if ($method !== self::get_request_method()) {
            return;
        }
        // For request methods that must not have a request body we fetch data
        // from the query string. Otherwise we look for data in the request body.
        if ('GET' === $method || 'HEAD' === $method || 'TRACE' === $method) {
            if ('' === $name) {
                $data = $_GET;
            } else {
                $query_data = $_GET[$name] ?? $missing_data;
                $data = $this->missing_data_handler->handle($form, $query_data);
                if ($missing_data === $data) {
                    // Don't submit GET requests if the form's name does not exist
                    // in the request
                    return;
                }
            }
        } else {
            // Mark the form with an error if the uploaded size was too large
            // This is done here and not in FormValidator because $_POST is
            // empty when that error occurs. Hence the form is never submitted.
            if ($this->server_params->has_post_max_size_been_exceeded()) {
                // Submit the form, but don't clear the default values
                $form->submit(null, false);
                $form->add_error(new Form_Error($form->get_config()->get_option('upload_max_size_message')(), null, ['{{ max }}' => $this->server_params->get_normalized_ini_post_max_size()]));
                return;
            }
            $fixed_files = [];
            foreach ($_FILES as $file_key => $file) {
                $fixed_files[$file_key] = self::strip_empty_files(self::fix_php_files_array($file));
            }
            if ('' === $name) {
                $params = $_POST;
                $files = $fixed_files;
            } elseif (\array_key_exists($name, $_POST) || \array_key_exists($name, $fixed_files)) {
                $default = $form->get_config()->get_compound() ? [] : null;
                $params = \array_key_exists($name, $_POST) ? $_POST[$name] : $default;
                $files = \array_key_exists($name, $fixed_files) ? $fixed_files[$name] : $default;
            } else {
                $params = $missing_data;
                $files = null;
            }
            if ('PATCH' !== $method) {
                $params = $this->missing_data_handler->handle($form, $params);
            }
            if ($missing_data === $params) {
                // Don't submit the form if it is not present in the request
                return;
            }
            if (\is_array($params) && \is_array($files)) {
                $data = Form_Util::merge_params_and_files($params, $files);
            } else {
                $data = $params ?: $files;
            }
        }
        // Don't auto-submit the form unless at least one field is present.
        if ('' === $name && \count(array_intersect_key($data, $form->all())) <= 0) {
            return;
        }
        if (\is_array($data) && \array_key_exists('_method', $data) && $method === $data['_method'] && !$form->has('_method')) {
            unset($data['_method']);
        }
        $form->submit($data, 'PATCH' !== $method);
    }
    public function is_file_upload(mixed $data): bool
    {
        // POST data will always be strings or arrays of strings. Thus, we can be sure
        // that the submitted data is a file upload if the "error" value is an integer
        // (this value must have been injected by PHP itself).
        return \is_array($data) && isset($data['error']) && \is_int($data['error']);
    }
    public function get_upload_file_error(mixed $data): ?int
    {
        if (!\is_array($data)) {
            return null;
        }
        if (!isset($data['error'])) {
            return null;
        }
        if (!\is_int($data['error'])) {
            return null;
        }
        if (\UPLOAD_ERR_OK === $data['error']) {
            return null;
        }
        return $data['error'];
    }
    private static function get_request_method(): string
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ('POST' === $method && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
        return $method;
    }
    /**
     * Fixes a malformed PHP $_FILES array.
     *
     * PHP has a bug that the format of the $_FILES array differs, depending on
     * whether the uploaded file fields had normal field names or array-like
     * field names ("normal" vs. "parent[child]").
     *
     * This method fixes the array to look like the "normal" $_FILES array.
     *
     * It's safe to pass an already converted array, in which case this method
     * just returns the original array unmodified.
     *
     * This method is identical to {@link \Symfony\Component\HttpFoundation\FileBag::fixPhpFilesArray}
     * and should be kept as such in order to port fixes quickly and easily.
     */
    private static function fix_php_files_array(mixed $data): mixed
    {
        if (!\is_array($data)) {
            return $data;
        }
        $keys = array_keys($data + ['full_path' => null]);
        sort($keys);
        if (self::FILE_KEYS !== $keys || !isset($data['name']) || !\is_array($data['name'])) {
            return $data;
        }
        $files = $data;
        foreach (self::FILE_KEYS as $k) {
            unset($files[$k]);
        }
        foreach ($data['name'] as $key => $name) {
            $files[$key] = self::fix_php_files_array(['error' => $data['error'][$key], 'name' => $name, 'type' => $data['type'][$key], 'tmp_name' => $data['tmp_name'][$key], 'size' => $data['size'][$key]] + (isset($data['full_path'][$key]) ? ['full_path' => $data['full_path'][$key]] : []));
        }
        return $files;
    }
    private static function strip_empty_files(mixed $data): mixed
    {
        if (!\is_array($data)) {
            return $data;
        }
        $keys = array_keys($data + ['full_path' => null]);
        sort($keys);
        if (self::FILE_KEYS === $keys) {
            if (\UPLOAD_ERR_NO_FILE === $data['error']) {
                return null;
            }
            return $data;
        }
        foreach ($data as $key => $value) {
            $data[$key] = self::strip_empty_files($value);
        }
        return $data;
    }
}