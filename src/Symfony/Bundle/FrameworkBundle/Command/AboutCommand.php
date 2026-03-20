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
namespace Symfony\Bundle\Framework_Bundle\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table_Separator;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Http_Kernel\Kernel;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * A console command to display information about the current installation.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 * @author Joppe De Cuyper <hello@joppe.dev>
 *
 * @final
 */
#[As_Command(name: 'about', description: 'Display information about the current project')]
class About_Command extends Command
{
    protected function configure(): void
    {
        $this->set_help(<<<'EOT'
        The <info>%command.name%</info> command displays information about the current Symfony project.
        
        The <info>PHP</info> section displays important configuration that could affect your application. The values might
        be different between web and CLI.
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        /** @var KernelInterface $kernel */
        $kernel = $this->get_application()->get_kernel();
        $build_dir = $kernel->get_build_dir();
        $share_dir = $kernel->get_share_dir();
        $xdebug_mode = getenv('XDEBUG_MODE') ?: \ini_get('xdebug.mode');
        $rows = [['<info>Symfony</>'], new Table_Separator(), ['Version', Kernel::VERSION], ['Long-Term Support', 4 === Kernel::MINOR_VERSION ? 'Yes' : 'No'], ['End of maintenance', Kernel::END_OF_MAINTENANCE . (self::is_expired(Kernel::END_OF_MAINTENANCE) ? ' <error>Expired</>' : ' (<comment>' . self::days_before_expiration(Kernel::END_OF_MAINTENANCE) . '</>)')], ['End of life', Kernel::END_OF_LIFE . (self::is_expired(Kernel::END_OF_LIFE) ? ' <error>Expired</>' : ' (<comment>' . self::days_before_expiration(Kernel::END_OF_LIFE) . '</>)')], new Table_Separator(), ['<info>Kernel</>'], new Table_Separator(), ['Type', $kernel::class], ['Environment', $kernel->get_environment()], ['Debug', $kernel->is_debug() ? 'true' : 'false'], ['Charset', $kernel->get_charset()], ['Cache directory', self::format_path($kernel->get_cache_dir(), $kernel->get_project_dir()) . ' (<comment>' . self::format_file_size($kernel->get_cache_dir()) . '</>)'], ['Build directory', self::format_path($build_dir, $kernel->get_project_dir()) . ' (<comment>' . self::format_file_size($build_dir) . '</>)'], ['Share directory', null === $share_dir ? 'none' : self::format_path($share_dir, $kernel->get_project_dir()) . ' (<comment>' . self::format_file_size($share_dir) . '</>)'], ['Log directory', self::format_path($kernel->get_log_dir(), $kernel->get_project_dir()) . ' (<comment>' . self::format_file_size($kernel->get_log_dir()) . '</>)'], new Table_Separator(), ['<info>PHP</>'], new Table_Separator(), ['Version', \PHP_VERSION], ['Architecture', \PHP_INT_SIZE * 8 . ' bits'], ['Intl locale', class_exists(\Locale::class, false) && \Locale::get_default() ? \Locale::get_default() : 'n/a'], ['Timezone', date_default_timezone_get() . ' (<comment>' . (new \DateTimeImmutable())->format(\DateTimeInterface::W3C) . '</>)'], ['OPcache', \extension_loaded('Zend OPcache') ? filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Not enabled' : 'Not installed'], ['APCu', \extension_loaded('apcu') ? filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Not enabled' : 'Not installed'], ['Xdebug', \extension_loaded('xdebug') ? $xdebug_mode && 'off' !== $xdebug_mode ? 'Enabled (' . $xdebug_mode . ')' : 'Not enabled' : 'Not installed']];
        $io->table([], $rows);
        return 0;
    }
    private static function format_path(string $path, string $base_dir): string
    {
        return preg_replace('~^' . preg_quote($base_dir, '~') . '~', '.', $path);
    }
    private static function format_file_size(string $path): string
    {
        if (is_file($path)) {
            $size = filesize($path) ?: 0;
        } else {
            if (!is_dir($path)) {
                return 'n/a';
            }
            $size = 0;
            foreach (new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($path, \Recursive_Directory_Iterator::SKIP_DOTS | \Recursive_Directory_Iterator::FOLLOW_SYMLINKS)) as $file) {
                if ($file->is_readable()) {
                    $size += $file->get_size();
                }
            }
        }
        return Helper::format_memory($size);
    }
    private static function is_expired(string $date): bool
    {
        $date = \DateTimeImmutable::create_from_format('d/m/Y', '01/' . $date);
        return false !== $date && new \DateTimeImmutable() > $date->modify('last day of this month 23:59:59');
    }
    private static function days_before_expiration(string $date): string
    {
        $date = \DateTimeImmutable::create_from_format('d/m/Y', '01/' . $date);
        return (new \DateTimeImmutable())->diff($date->modify('last day of this month 23:59:59'))->format('in %R%a days');
    }
}