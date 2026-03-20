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
namespace Symfony\Component\Form\Extension\Http_Foundation;

use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Missing_Data_Handler;
use Symfony\Component\Form\Request_Handler_Interface;
use Symfony\Component\Form\Util\Form_Util;
use Symfony\Component\Form\Util\Server_Params;
use Symfony\Component\Http_Foundation\File\File;
use Symfony\Component\Http_Foundation\File\Uploaded_File;
use Symfony\Component\Http_Foundation\Request;
/**
 * A request processor using the {@link Request} class of the HttpFoundation
 * component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Http_Foundation_Request_Handler implements Request_Handler_Interface
{
    private readonly Missing_Data_Handler $missing_data_handler;
    public function __construct(private readonly ?Server_Params $server_params = new Server_Params())
    {
        $this->missing_data_handler = new Missing_Data_Handler();
    }
    public function handle_request(Form_Interface $form, mixed $request = null): void
    {
        if (!$request instanceof Request) {
            throw new Unexpected_Type_Exception($request, Request::class);
        }
        $name = $form->get_name();
        $method = $form->get_config()->get_method();
        $missing_data = $this->missing_data_handler->missing_data;
        if ($method !== $request->get_method()) {
            return;
        }
        // For request methods that must not have a request body we fetch data
        // from the query string. Otherwise we look for data in the request body.
        if ('GET' === $method || 'HEAD' === $method || 'TRACE' === $method) {
            if ('' === $name) {
                $data = $request->query->all();
            } else {
                $query_data = $request->query->all()[$name] ?? $missing_data;
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
            if ('' === $name) {
                $params = $request->request->all();
                $files = $request->files->all();
            } elseif ($request->request->has($name) || $request->files->has($name)) {
                $default = $form->get_config()->get_compound() ? [] : null;
                $params = $request->request->all()[$name] ?? $default;
                $files = $request->files->get($name, $default);
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
        $form->submit($data, 'PATCH' !== $method);
    }
    public function is_file_upload(mixed $data): bool
    {
        return $data instanceof File;
    }
    public function get_upload_file_error(mixed $data): ?int
    {
        if (!$data instanceof Uploaded_File || $data->is_valid()) {
            return null;
        }
        return $data->get_error();
    }
}