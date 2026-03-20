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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Filesystem\Exception\Io_Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * Command that places bundle web assets into a given directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Gábor Egyed <gabor.egyed@gmail.com>
 *
 * @final
 */
#[As_Command(name: 'assets:install', description: 'Install bundle\'s web assets under a public directory')]
class Assets_Install_Command extends Command
{
    public const METHOD_COPY = 'copy';
    public const METHOD_ABSOLUTE_SYMLINK = 'absolute symlink';
    public const METHOD_RELATIVE_SYMLINK = 'relative symlink';
    public function __construct(private readonly Filesystem $filesystem, private readonly string $project_dir)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('target', Input_Argument::OPTIONAL, 'The target directory')])->add_option('symlink', null, Input_Option::VALUE_NONE, 'Symlink the assets instead of copying them')->add_option('relative', null, Input_Option::VALUE_NONE, 'Make relative symlinks')->add_option('no-cleanup', null, Input_Option::VALUE_NONE, 'Do not remove the assets of the bundles that no longer exist')->set_help(<<<'EOT'
        The <info>%command.name%</info> command installs bundle assets into a given
        directory (e.g. the <comment>public</comment> directory).
        
          <info>php %command.full_name% public</info>
        
        A "bundles" directory will be created inside the target directory and the
        "Resources/public" directory of each bundle will be copied into it.
        
        To create a symlink to each bundle instead of copying its assets, use the
        <info>--symlink</info> option (will fall back to hard copies when symbolic links aren't possible:
        
          <info>php %command.full_name% public --symlink</info>
        
        To make symlink relative, add the <info>--relative</info> option:
        
          <info>php %command.full_name% public --symlink --relative</info>
        
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->get_application()->get_kernel();
        $target_arg = rtrim($input->get_argument('target') ?? '', '/');
        if (!$target_arg) {
            $target_arg = $this->get_public_directory($kernel->get_container());
        }
        if (!is_dir($target_arg)) {
            $target_arg = $kernel->get_project_dir() . '/' . $target_arg;
            if (!is_dir($target_arg)) {
                throw new InvalidArgumentException(\sprintf('The target directory "%s" does not exist.', $target_arg));
            }
        }
        $bundles_dir = $target_arg . '/bundles/';
        $io = new Symfony_Style($input, $output);
        $io->new_line();
        if ($input->get_option('relative')) {
            $expected_method = self::METHOD_RELATIVE_SYMLINK;
            $io->text('Trying to install assets as <info>relative symbolic links</info>.');
        } elseif ($input->get_option('symlink')) {
            $expected_method = self::METHOD_ABSOLUTE_SYMLINK;
            $io->text('Trying to install assets as <info>absolute symbolic links</info>.');
        } else {
            $expected_method = self::METHOD_COPY;
            $io->text('Installing assets as <info>hard copies</info>.');
        }
        $io->new_line();
        $rows = [];
        $copy_used = false;
        $exit_code = 0;
        $valid_asset_dirs = [];
        foreach ($kernel->get_bundles() as $bundle) {
            if (!is_dir($origin_dir = $bundle->get_path() . '/Resources/public') && !is_dir($origin_dir = $bundle->get_path() . '/public')) {
                continue;
            }
            $asset_dir = preg_replace('/bundle$/', '', strtolower($bundle->get_name()));
            $target_dir = $bundles_dir . $asset_dir;
            $valid_asset_dirs[] = $asset_dir;
            if (Output_Interface::VERBOSITY_VERBOSE <= $output->get_verbosity()) {
                $message = \sprintf("%s\n-> %s", $bundle->get_name(), $target_dir);
            } else {
                $message = $bundle->get_name();
            }
            try {
                $this->filesystem->remove($target_dir);
                if (self::METHOD_RELATIVE_SYMLINK === $expected_method) {
                    $method = $this->relative_symlink_with_fallback($origin_dir, $target_dir);
                } elseif (self::METHOD_ABSOLUTE_SYMLINK === $expected_method) {
                    $method = $this->absolute_symlink_with_fallback($origin_dir, $target_dir);
                } else {
                    $method = $this->hard_copy($origin_dir, $target_dir);
                }
                if (self::METHOD_COPY === $method) {
                    $copy_used = true;
                }
                if ($method === $expected_method) {
                    $rows[] = [\sprintf('<fg=green;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'OK' : "✔"), $message, $method];
                } else {
                    $rows[] = [\sprintf('<fg=yellow;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'WARNING' : '!'), $message, $method];
                }
            } catch (\Exception $e) {
                $exit_code = 1;
                $rows[] = [\sprintf('<fg=red;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'ERROR' : "✘"), $message, $e->get_message()];
            }
        }
        // remove the assets of the bundles that no longer exist
        if (!$input->get_option('no-cleanup') && is_dir($bundles_dir)) {
            $dirs_to_remove = Finder::create()->depth(0)->directories()->exclude($valid_asset_dirs)->in($bundles_dir);
            $this->filesystem->remove($dirs_to_remove);
        }
        if ($rows) {
            $io->table(['', 'Bundle', 'Method / Error'], $rows);
        }
        if (0 !== $exit_code) {
            $io->error('Some errors occurred while installing assets.');
        } else {
            if ($copy_used) {
                $io->note('Some assets were installed via copy. If you make changes to these assets you have to run this command again.');
            }
            $io->success($rows ? 'All assets were successfully installed.' : 'No assets were provided by any bundle.');
        }
        return $exit_code;
    }
    /**
     * Try to create relative symlink.
     *
     * Falling back to absolute symlink and finally hard copy.
     */
    private function relative_symlink_with_fallback(string $origin_dir, string $target_dir): string
    {
        try {
            $this->symlink($origin_dir, $target_dir, true);
            $method = self::METHOD_RELATIVE_SYMLINK;
        } catch (Io_Exception) {
            $method = $this->absolute_symlink_with_fallback($origin_dir, $target_dir);
        }
        return $method;
    }
    /**
     * Try to create absolute symlink.
     *
     * Falling back to hard copy.
     */
    private function absolute_symlink_with_fallback(string $origin_dir, string $target_dir): string
    {
        try {
            $this->symlink($origin_dir, $target_dir);
            $method = self::METHOD_ABSOLUTE_SYMLINK;
        } catch (Io_Exception) {
            // fall back to copy
            $method = $this->hard_copy($origin_dir, $target_dir);
        }
        return $method;
    }
    /**
     * Creates symbolic link.
     *
     * @throws IOException if link cannot be created
     */
    private function symlink(string $origin_dir, string $target_dir, bool $relative = false): void
    {
        if ($relative) {
            $this->filesystem->mkdir(\dirname($target_dir));
            $origin_dir = $this->filesystem->make_path_relative($origin_dir, realpath(\dirname($target_dir)));
        }
        $this->filesystem->symlink($origin_dir, $target_dir);
        if (!file_exists($target_dir)) {
            throw new Io_Exception(\sprintf('Symbolic link "%s" was created but appears to be broken.', $target_dir), 0, null, $target_dir);
        }
    }
    /**
     * Copies origin to target.
     */
    private function hard_copy(string $origin_dir, string $target_dir): string
    {
        $this->filesystem->mkdir($target_dir, 0777);
        // We use a custom iterator to ignore VCS files
        $this->filesystem->mirror($origin_dir, $target_dir, Finder::create()->ignore_dot_files(false)->in($origin_dir));
        return self::METHOD_COPY;
    }
    private function get_public_directory(Container_Interface $container): string
    {
        $default_public_dir = 'public';
        if (null === $this->project_dir && !$container->has_parameter('kernel.project_dir')) {
            return $default_public_dir;
        }
        $composer_file_path = ($this->project_dir ?? $container->get_parameter('kernel.project_dir')) . '/composer.json';
        if (!file_exists($composer_file_path)) {
            return $default_public_dir;
        }
        $composer_config = json_decode($this->filesystem->read_file($composer_file_path), true, flags: \JSON_THROW_ON_ERROR);
        return $composer_config['extra']['public-dir'] ?? $default_public_dir;
    }
}