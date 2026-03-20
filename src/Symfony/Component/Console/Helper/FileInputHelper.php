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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\Invalid_File_Exception;
use Symfony\Component\Console\Exception\Missing_Input_Exception;
use Symfony\Component\Console\Input\File\Input_File;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\File_Question;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Terminal\Image\Image_Protocol_Interface;
use Symfony\Component\Console\Terminal\Image\I_Term2protocol;
use Symfony\Component\Console\Terminal\Image\Kitty_Graphics_Protocol;
/**
 * Orchestrates file input handling through paste detection or path input.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class File_Input_Helper
{
    private const BPM_ENABLE = "\x1b[?2004h";
    private const BPM_DISABLE = "\x1b[?2004l";
    private const PASTE_START = "\x1b[200~";
    private const PASTE_END = "\x1b[201~";
    private ?Image_Protocol_Interface $protocol = null;
    /**
     * @param resource $inputStream
     */
    public function read_file_input($input_stream, Output_Interface $output, File_Question $question): Input_File
    {
        if ($can_paste = $question->is_paste_allowed() && Terminal::supports_image_protocol() && Terminal::has_stty_available()) {
            $this->protocol = $this->detect_protocol();
        }
        $file = null;
        $input_helper = null;
        try {
            if ($can_paste) {
                $input_helper = new Terminal_Input_Helper($input_stream);
                $output->write(self::BPM_ENABLE);
                shell_exec('stty -icanon -echo');
                $file = $this->read_with_paste_detection($input_stream, $question, $input_helper);
            } elseif ($question->is_path_allowed()) {
                $file = $this->read_path_input($input_stream);
            } else {
                throw new Missing_Input_Exception('Terminal does not support image paste and path input is disabled.');
            }
        } finally {
            if ($can_paste) {
                $output->write(self::BPM_DISABLE);
                $input_helper?->finish();
            }
        }
        if (!$file->is_valid()) {
            throw new Invalid_File_Exception(\sprintf('File "%s" is not valid or readable.', $file->get_pathname()));
        }
        $this->display_file($output, $file);
        return $file;
    }
    public function display_file(Output_Interface $output, Input_File $file): void
    {
        $link = \sprintf('<href=file://%s>%s</>', $file->get_real_path(), $file->get_filename());
        if ($output->is_very_verbose()) {
            $output->writeln(\sprintf('<info>%s</info> %s (<comment>%s, %s</comment>)', "📎", $link, $file->get_mime_type() ?? 'unknown', $file->get_human_readable_size()));
        } else {
            $output->writeln(\sprintf('<info>%s</info> %s', "📎", $link));
        }
        if (Terminal::supports_image_protocol() && $this->is_displayable_image($file)) {
            $this->display_thumbnail($output, $file);
        }
    }
    /**
     * @param resource $inputStream
     */
    private function read_with_paste_detection($input_stream, File_Question $question, Terminal_Input_Helper $input_helper): Input_File
    {
        $buffer = '';
        $in_paste = false;
        $paste_buffer = '';
        while (!feof($input_stream)) {
            $input_helper->wait_for_input();
            $char = fread($input_stream, 1);
            if (false === $char || '' === $char) {
                if ('' === $buffer && '' === $paste_buffer) {
                    throw new Missing_Input_Exception('Aborted.');
                }
                break;
            }
            $buffer .= $char;
            if (!$in_paste && str_ends_with($buffer, self::PASTE_START)) {
                $in_paste = true;
                $buffer = substr($buffer, 0, -\strlen(self::PASTE_START));
                continue;
            }
            if ($in_paste && str_ends_with($buffer, self::PASTE_END)) {
                $paste_buffer = substr($buffer, 0, -\strlen(self::PASTE_END));
                break;
            }
            if (!$in_paste && ("\n" === $char || "\r" === $char)) {
                $buffer = rtrim($buffer, "\r\n");
                break;
            }
        }
        if ('' !== $paste_buffer) {
            if (null !== $this->protocol && $this->protocol->detect_pasted_image($paste_buffer)) {
                $decoded = $this->protocol->decode($paste_buffer);
                if ('' !== $decoded['data']) {
                    return Input_File::from_data($decoded['data'], $decoded['format']);
                }
            }
            $path = trim($paste_buffer);
            if ('' !== $path && $question->is_path_allowed()) {
                return Input_File::from_path($path);
            }
        }
        $path = trim($buffer);
        if ('' !== $path && $question->is_path_allowed()) {
            return Input_File::from_path($path);
        }
        throw new Missing_Input_Exception('No file input provided.');
    }
    /**
     * @param resource $inputStream
     */
    private function read_path_input($input_stream): Input_File
    {
        if (!$is_blocked = stream_get_meta_data($input_stream)['blocked'] ?? true) {
            stream_set_blocking($input_stream, true);
        }
        $path = fgets($input_stream);
        if (!$is_blocked) {
            stream_set_blocking($input_stream, false);
        }
        if (false === $path) {
            throw new Missing_Input_Exception('Aborted.');
        }
        if ('' === $path = trim($path)) {
            throw new Missing_Input_Exception('No file path provided.');
        }
        return Input_File::from_path($path);
    }
    private function detect_protocol(): ?Image_Protocol_Interface
    {
        if (Terminal::supports_kitty_graphics()) {
            return new Kitty_Graphics_Protocol();
        }
        if (Terminal::supports_i_term2images()) {
            return new I_Term2protocol();
        }
        return null;
    }
    private function is_displayable_image(Input_File $file): bool
    {
        if (null === $mime_type = $file->get_mime_type()) {
            return false;
        }
        return str_starts_with($mime_type, 'image/');
    }
    private function display_thumbnail(Output_Interface $output, Input_File $file): void
    {
        try {
            $contents = $file->get_contents();
        } catch (Invalid_File_Exception) {
            return;
        }
        $protocol = Terminal::supports_kitty_graphics() ? new Kitty_Graphics_Protocol() : new I_Term2protocol();
        $output->write($protocol->encode($contents, 16));
        $output->writeln('');
    }
}