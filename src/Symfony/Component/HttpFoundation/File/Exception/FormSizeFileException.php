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
namespace Symfony\Component\Http_Foundation\File\Exception;

/**
 * Thrown when an UPLOAD_ERR_FORM_SIZE error occurred with UploadedFile.
 *
 * @author Florent Mata <florentmata@gmail.com>
 */
class Form_Size_File_Exception extends File_Exception
{
}