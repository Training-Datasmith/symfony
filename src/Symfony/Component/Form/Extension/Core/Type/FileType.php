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
namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Event\Pre_Submit_Event;
use Symfony\Component\Form\File_Upload_Error;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Http_Foundation\File\File;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Contracts\Translation\Translator_Interface;
class File_Type extends Abstract_Type
{
    public const KIB_BYTES = 1024;
    public const MIB_BYTES = 1048576;
    private const SUFFIXES = [1 => 'bytes', self::KIB_BYTES => 'KiB', self::MIB_BYTES => 'MiB'];
    public function __construct(private readonly ?Translator_Interface $translator = null)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        // Ensure that submitted data is always an uploaded file or an array of some
        $builder->add_event_listener(Form_Events::PRE_SUBMIT, function (Form_Event $event) use ($options): void {
            /** @var PreSubmitEvent $event */
            $form = $event->get_form();
            $request_handler = $form->get_config()->get_request_handler();
            if ($options['multiple']) {
                $data = [];
                $files = $event->get_data();
                if (!\is_array($files)) {
                    $files = [];
                }
                foreach ($files as $file) {
                    if ($request_handler->is_file_upload($file)) {
                        $data[] = $file;
                        if (method_exists($request_handler, 'getUploadFileError') && null !== $error_code = $request_handler->get_upload_file_error($file)) {
                            $form->add_error($this->get_file_upload_error($error_code));
                        }
                    }
                }
                // Since the array is never considered empty in the view data format
                // on submission, we need to evaluate the configured empty data here
                if ([] === $data) {
                    $empty_data = $form->get_config()->get_empty_data();
                    $data = $empty_data instanceof \Closure ? $empty_data($form, $data) : $empty_data;
                }
                $event->set_data($data);
            } elseif ($request_handler->is_file_upload($event->get_data()) && method_exists($request_handler, 'getUploadFileError') && null !== $error_code = $request_handler->get_upload_file_error($event->get_data())) {
                $form->add_error($this->get_file_upload_error($error_code));
            } elseif (!$request_handler->is_file_upload($event->get_data())) {
                $event->set_data(null);
            }
        });
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
            $view->vars['attr']['multiple'] = 'multiple';
        }
        $view->vars = array_replace($view->vars, ['type' => 'file', 'value' => '']);
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['multipart'] = true;
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $data_class = null;
        if (class_exists(File::class)) {
            $data_class = static fn(Options $options): ?string => $options['multiple'] ? null : File::class;
        }
        $empty_data = static fn(Options $options): ?array => $options['multiple'] ? [] : null;
        $resolver->set_defaults(['compound' => false, 'data_class' => $data_class, 'empty_data' => $empty_data, 'multiple' => false, 'allow_file_upload' => true, 'invalid_message' => 'Please select a valid file.']);
    }
    public function get_block_prefix(): string
    {
        return 'file';
    }
    private function get_file_upload_error(int $error_code): File_Upload_Error
    {
        $message_parameters = [];
        if (\UPLOAD_ERR_INI_SIZE === $error_code) {
            [$limit_as_string, $suffix] = $this->factorize_sizes(0, self::get_max_filesize());
            $message_template = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
            $message_parameters = ['{{ limit }}' => $limit_as_string, '{{ suffix }}' => $suffix];
        } elseif (\UPLOAD_ERR_FORM_SIZE === $error_code) {
            $message_template = 'The file is too large.';
        } else {
            $message_template = 'The file could not be uploaded.';
        }
        if (null !== $this->translator) {
            $message = $this->translator->trans($message_template, $message_parameters, 'validators');
        } else {
            $message = strtr($message_template, $message_parameters);
        }
        return new File_Upload_Error($message, $message_template, $message_parameters);
    }
    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     *
     * This method should be kept in sync with Symfony\Component\HttpFoundation\File\UploadedFile::getMaxFilesize().
     */
    private static function get_max_filesize(): int
    {
        $ini_max = strtolower(\ini_get('upload_max_filesize'));
        if ('' === $ini_max) {
            return \PHP_INT_MAX;
        }
        $max = ltrim($ini_max, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }
        switch (substr($ini_max, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }
        return $max;
    }
    /**
     * Converts the limit to the smallest possible number
     * (i.e. try "MB", then "kB", then "bytes").
     *
     * This method should be kept in sync with Symfony\Component\Validator\Constraints\FileValidator::factorizeSizes().
     */
    private function factorize_sizes(int $size, int|float $limit): array
    {
        $coef = self::MIB_BYTES;
        $coef_factor = self::KIB_BYTES;
        $limit_as_string = (string) ($limit / $coef);
        // Restrict the limit to 2 decimals (without rounding! we
        // need the precise value)
        while (self::more_decimals_than($limit_as_string, 2)) {
            $coef /= $coef_factor;
            $limit_as_string = (string) ($limit / $coef);
        }
        // Convert size to the same measure, but round to 2 decimals
        $size_as_string = (string) round($size / $coef, 2);
        // If the size and limit produce the same string output
        // (due to rounding), reduce the coefficient
        while ($size_as_string === $limit_as_string) {
            $coef /= $coef_factor;
            $limit_as_string = (string) ($limit / $coef);
            $size_as_string = (string) round($size / $coef, 2);
        }
        return [$limit_as_string, self::SUFFIXES[$coef]];
    }
    /**
     * This method should be kept in sync with Symfony\Component\Validator\Constraints\FileValidator::moreDecimalsThan().
     */
    private static function more_decimals_than(string $double, int $number_of_decimals): bool
    {
        return \strlen($double) > \strlen(round($double, $number_of_decimals));
    }
}