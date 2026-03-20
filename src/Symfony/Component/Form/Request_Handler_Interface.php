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

/**
 * Submits forms if they were submitted.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Request_Handler_Interface
{
    /**
     * Submits a form if it was submitted.
     */
    public function handle_request(Form_Interface $form, mixed $request = null): void;
    /**
     * Returns true if the given data is a file upload.
     */
    public function is_file_upload(mixed $data): bool;
}