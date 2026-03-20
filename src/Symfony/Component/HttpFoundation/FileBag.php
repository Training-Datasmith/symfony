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

use Symfony\Component\Http_Foundation\File\Uploaded_File;
/**
 * FileBag is a container for uploaded files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class File_Bag extends Parameter_Bag
{
    private const FILE_KEYS = ['error', 'full_path', 'name', 'size', 'tmp_name', 'type'];
    /**
     * @param array|UploadedFile[] $parameters An array of HTTP files
     */
    public function __construct(array $parameters = [])
    {
        $this->replace($parameters);
    }
    public function replace(array $files = []): void
    {
        $this->parameters = [];
        $this->add($files);
    }
    public function set(string $key, mixed $value): void
    {
        if (!\is_array($value) && !$value instanceof Uploaded_File) {
            throw new \InvalidArgumentException('An uploaded file must be an array or an instance of UploadedFile.');
        }
        parent::set($key, $this->convert_file_information($value));
    }
    public function add(array $files = []): void
    {
        foreach ($files as $key => $file) {
            $this->set($key, $file);
        }
    }
    /**
     * Converts uploaded files to UploadedFile instances.
     *
     * @return UploadedFile[]|UploadedFile|null
     */
    protected function convert_file_information(array|Uploaded_File $file): array|Uploaded_File|null
    {
        if ($file instanceof Uploaded_File) {
            return $file;
        }
        $file = $this->fix_php_files_array($file);
        $keys = array_keys($file + ['full_path' => null]);
        sort($keys);
        if (self::FILE_KEYS === $keys) {
            if (\UPLOAD_ERR_NO_FILE === $file['error']) {
                $file = null;
            } else {
                $file = new Uploaded_File($file['tmp_name'], $file['full_path'] ?? $file['name'], $file['type'], $file['error'], false);
            }
        } else {
            $file = array_map(fn($v) => $v instanceof Uploaded_File || \is_array($v) ? $this->convert_file_information($v) : $v, $file);
            if (array_is_list($file)) {
                $file = array_filter($file);
            }
        }
        return $file;
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
     */
    protected function fix_php_files_array(array $data): array
    {
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
            $files[$key] = $this->fix_php_files_array(['error' => $data['error'][$key], 'name' => $name, 'type' => $data['type'][$key], 'tmp_name' => $data['tmp_name'][$key], 'size' => $data['size'][$key]] + (isset($data['full_path'][$key]) ? ['full_path' => $data['full_path'][$key]] : []));
        }
        return $files;
    }
}