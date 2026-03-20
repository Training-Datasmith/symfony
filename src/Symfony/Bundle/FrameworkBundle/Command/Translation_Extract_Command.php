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
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Http_Kernel\Kernel_Interface;
use Symfony\Component\Translation\Catalogue\Merge_Operation;
use Symfony\Component\Translation\Catalogue\Target_Operation;
use Symfony\Component\Translation\Extractor\Extractor_Interface;
use Symfony\Component\Translation\Message_Catalogue;
use Symfony\Component\Translation\Message_Catalogue_Interface;
use Symfony\Component\Translation\Reader\Translation_Reader_Interface;
use Symfony\Component\Translation\Writer\Translation_Writer_Interface;
/**
 * A command that parses templates to extract translation messages and adds them
 * into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
#[As_Command(name: 'translation:extract', description: 'Extract missing translations keys from code to translation files')]
class Translation_Extract_Command extends Command
{
    private const ASC = 'asc';
    private const DESC = 'desc';
    private const SORT_ORDERS = [self::ASC, self::DESC];
    private const FORMATS = ['xlf12' => ['xlf', '1.2'], 'xlf20' => ['xlf', '2.0']];
    private const NO_FILL_PREFIX = "\x00NoFill\x00";
    public function __construct(private readonly Translation_Writer_Interface $writer, private readonly Translation_Reader_Interface $reader, private readonly Extractor_Interface $extractor, private readonly string $default_locale, private readonly ?string $default_trans_path = null, private readonly ?string $default_views_path = null, private readonly array $trans_paths = [], private readonly array $code_paths = [], private array $enabled_locales = [])
    {
        $this->enabled_locales = array_filter($enabled_locales);
        parent::__construct();
        if (!method_exists($writer, 'getFormats')) {
            throw new \InvalidArgumentException(\sprintf('The writer class "%s" does not implement the "getFormats()" method.', $writer::class));
        }
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('locale', Input_Argument::REQUIRED, 'The locale'), new Input_Argument('bundle', Input_Argument::OPTIONAL, 'The bundle name or directory where to load the messages'), new Input_Option('prefix', null, Input_Option::VALUE_REQUIRED, 'Override the default prefix', '__'), new Input_Option('no-fill', null, Input_Option::VALUE_NONE, 'Extract translation keys without filling in values'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, 'Override the default output format', 'xlf12'), new Input_Option('dump-messages', null, Input_Option::VALUE_NONE, 'Should the messages be dumped in the console'), new Input_Option('force', null, Input_Option::VALUE_NONE, 'Should the extract be done'), new Input_Option('clean', null, Input_Option::VALUE_NONE, 'Should clean not found messages'), new Input_Option('domain', null, Input_Option::VALUE_REQUIRED, 'Specify the domain to extract'), new Input_Option('sort', null, Input_Option::VALUE_REQUIRED, 'Return list of messages sorted alphabetically'), new Input_Option('as-tree', null, Input_Option::VALUE_REQUIRED, 'Dump the messages as a tree-like structure: The given value defines the level where to switch to inline YAML')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command extracts translation strings from templates
        of a given bundle or the default translations directory. It can display them or merge
        the new ones into the translation files.
        
        When new translation strings are found it can automatically add a prefix to the translation
        message. However, if the <info>--no-fill</info> option is used, the <info>--prefix</info>
        option has no effect, since the translation values are left empty.
        
        Example running against a Bundle (AcmeBundle)
        
          <info>php %command.full_name% --dump-messages en AcmeBundle</info>
          <info>php %command.full_name% --force --prefix="new_" fr AcmeBundle</info>
        
        Example running against default messages directory
        
          <info>php %command.full_name% --dump-messages en</info>
          <info>php %command.full_name% --force --prefix="new_" fr</info>
        
        You can sort the output with the <info>--sort</> flag:
        
            <info>php %command.full_name% --dump-messages --sort=asc en AcmeBundle</info>
            <info>php %command.full_name% --force --sort=desc fr</info>
        
        You can dump a tree-like structure using the yaml format with <info>--as-tree</> flag:
        
            <info>php %command.full_name% --force --format=yaml --as-tree=3 en AcmeBundle</info>
        
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $error_io = $io->get_error_style();
        // check presence of force or dump-message
        if (true !== $input->get_option('force') && true !== $input->get_option('dump-messages')) {
            $error_io->error('You must choose one of --force or --dump-messages');
            return 1;
        }
        $format = $input->get_option('format');
        $xliff_version = '1.2';
        if (\array_key_exists($format, self::FORMATS)) {
            [$format, $xliff_version] = self::FORMATS[$format];
        }
        // check format
        $supported_formats = $this->writer->get_formats();
        if (!\in_array($format, $supported_formats, true)) {
            $error_io->error(['Wrong output format', 'Supported formats are: ' . implode(', ', $supported_formats) . ', xlf12 and xlf20.']);
            return 1;
        }
        /** @var KernelInterface $kernel */
        $kernel = $this->get_application()->get_kernel();
        // Define Root Paths
        $trans_paths = $this->get_root_trans_paths();
        $code_paths = $this->get_root_code_paths($kernel);
        $current_name = 'default directory';
        // Override with provided Bundle info
        if (null !== $input->get_argument('bundle')) {
            try {
                $found_bundle = $kernel->get_bundle($input->get_argument('bundle'));
                $bundle_dir = $found_bundle->get_path();
                $trans_paths = [is_dir($bundle_dir . '/Resources/translations') ? $bundle_dir . '/Resources/translations' : $bundle_dir . '/translations'];
                $code_paths = [is_dir($bundle_dir . '/Resources/views') ? $bundle_dir . '/Resources/views' : $bundle_dir . '/templates'];
                if ($this->default_trans_path) {
                    $trans_paths[] = $this->default_trans_path;
                }
                if ($this->default_views_path) {
                    $code_paths[] = $this->default_views_path;
                }
                $current_name = $found_bundle->get_name();
            } catch (\InvalidArgumentException) {
                // such a bundle does not exist, so treat the argument as path
                $path = $input->get_argument('bundle');
                $trans_paths = [$path . '/translations'];
                $code_paths = [$path . '/templates'];
                if (!is_dir($trans_paths[0])) {
                    throw new InvalidArgumentException(\sprintf('"%s" is neither an enabled bundle nor a directory.', $trans_paths[0]));
                }
            }
        }
        $io->title('Translation Messages Extractor and Dumper');
        $io->comment(\sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->get_argument('locale'), $current_name));
        $io->comment('Parsing templates...');
        $prefix = $input->get_option('no-fill') ? self::NO_FILL_PREFIX : $input->get_option('prefix');
        $extracted_catalogue = $this->extract_messages($input->get_argument('locale'), $code_paths, $prefix);
        $io->comment('Loading translation files...');
        $current_catalogue = $this->load_current_messages($input->get_argument('locale'), $trans_paths);
        if (null !== $domain = $input->get_option('domain')) {
            $current_catalogue = $this->filter_catalogue($current_catalogue, $domain);
            $extracted_catalogue = $this->filter_catalogue($extracted_catalogue, $domain);
        }
        // process catalogues
        $operation = $input->get_option('clean') ? new Target_Operation($current_catalogue, $extracted_catalogue) : new Merge_Operation($current_catalogue, $extracted_catalogue);
        // Exit if no messages found.
        if (!\count($operation->get_domains())) {
            $error_io->warning('No translation messages were found.');
            return 0;
        }
        $result_message = 'Translation files were successfully updated';
        $operation->move_messages_to_intl_domains_if_possible('new');
        if ($sort = $input->get_option('sort')) {
            $sort = strtolower((string) $sort);
            if (!\in_array($sort, self::SORT_ORDERS, true)) {
                $error_io->error(['Wrong sort order', 'Supported formats are: ' . implode(', ', self::SORT_ORDERS) . '.']);
                return 1;
            }
        }
        // show compiled list of messages
        if (true === $input->get_option('dump-messages')) {
            $extracted_messages_count = 0;
            $io->new_line();
            foreach ($operation->get_domains() as $domain) {
                $new_keys = array_keys($operation->get_new_messages($domain));
                $all_keys = array_keys($operation->get_messages($domain));
                $list = array_merge(array_diff($all_keys, $new_keys), array_map(static fn(int|string $id): string => \sprintf('<fg=green>%s</>', $id), $new_keys), array_map(static fn(int|string $id): string => \sprintf('<fg=red>%s</>', $id), array_keys($operation->get_obsolete_messages($domain))));
                $domain_messages_count = \count($list);
                if (self::DESC === $sort) {
                    rsort($list);
                } else {
                    sort($list);
                }
                $io->section(\sprintf('Messages extracted for domain "<info>%s</info>" (%d message%s)', $domain, $domain_messages_count, $domain_messages_count > 1 ? 's' : ''));
                $io->listing($list);
                $extracted_messages_count += $domain_messages_count;
            }
            if ('xlf' === $format) {
                $io->comment(\sprintf('Xliff output version is <info>%s</info>', $xliff_version));
            }
            $result_message = \sprintf('%d message%s successfully extracted', $extracted_messages_count, $extracted_messages_count > 1 ? 's were' : ' was');
        }
        // save the files
        if (true === $input->get_option('force')) {
            $io->comment('Writing files...');
            $bundle_trans_path = false;
            foreach ($trans_paths as $path) {
                if (is_dir($path)) {
                    $bundle_trans_path = $path;
                }
            }
            if (!$bundle_trans_path) {
                $bundle_trans_path = end($trans_paths);
            }
            $operation_result = $operation->get_result();
            if ($sort) {
                $operation_result = $this->sort_catalogue($operation_result, $sort);
            }
            if (true === $input->get_option('no-fill')) {
                $this->remove_no_fill_translations($operation_result);
            }
            $this->writer->write($operation_result, $format, ['path' => $bundle_trans_path, 'default_locale' => $this->default_locale, 'xliff_version' => $xliff_version, 'as_tree' => $input->get_option('as-tree'), 'inline' => $input->get_option('as-tree') ?? 0]);
            if (true === $input->get_option('dump-messages')) {
                $result_message .= ' and translation files were updated';
            }
        }
        $io->success($result_message . '.');
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('locale')) {
            $suggestions->suggest_values($this->enabled_locales);
            return;
        }
        /** @var KernelInterface $kernel */
        $kernel = $this->get_application()->get_kernel();
        if ($input->must_suggest_argument_values_for('bundle')) {
            $bundles = [];
            foreach ($kernel->get_bundles() as $bundle) {
                $bundles[] = $bundle->get_name();
                if ($bundle->get_container_extension()) {
                    $bundles[] = $bundle->get_container_extension()->get_alias();
                }
            }
            $suggestions->suggest_values($bundles);
            return;
        }
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values(array_merge($this->writer->get_formats(), array_keys(self::FORMATS)));
            return;
        }
        if ($input->must_suggest_option_values_for('domain') && $locale = $input->get_argument('locale')) {
            $extracted_catalogue = $this->extract_messages($locale, $this->get_root_code_paths($kernel), $input->get_option('prefix'));
            $current_catalogue = $this->load_current_messages($locale, $this->get_root_trans_paths());
            // process catalogues
            $operation = $input->get_option('clean') ? new Target_Operation($current_catalogue, $extracted_catalogue) : new Merge_Operation($current_catalogue, $extracted_catalogue);
            $suggestions->suggest_values($operation->get_domains());
            return;
        }
        if ($input->must_suggest_option_values_for('sort')) {
            $suggestions->suggest_values(self::SORT_ORDERS);
        }
    }
    private function filter_catalogue(Message_Catalogue $catalogue, string $domain): Message_Catalogue
    {
        $filtered_catalogue = new Message_Catalogue($catalogue->get_locale());
        // extract intl-icu messages only
        $intl_domain = $domain . Message_Catalogue_Interface::INTL_DOMAIN_SUFFIX;
        if ($intl_messages = $catalogue->all($intl_domain)) {
            $filtered_catalogue->add($intl_messages, $intl_domain);
        }
        // extract all messages and subtract intl-icu messages
        if ($messages = array_diff($catalogue->all($domain), $intl_messages)) {
            $filtered_catalogue->add($messages, $domain);
        }
        foreach ($catalogue->get_resources() as $resource) {
            $filtered_catalogue->add_resource($resource);
        }
        if ($metadata = $catalogue->get_metadata('', $intl_domain)) {
            foreach ($metadata as $k => $v) {
                $filtered_catalogue->set_metadata($k, $v, $intl_domain);
            }
        }
        if ($metadata = $catalogue->get_metadata('', $domain)) {
            foreach ($metadata as $k => $v) {
                $filtered_catalogue->set_metadata($k, $v, $domain);
            }
        }
        return $filtered_catalogue;
    }
    private function sort_catalogue(Message_Catalogue $catalogue, string $sort): Message_Catalogue
    {
        $sorted_catalogue = new Message_Catalogue($catalogue->get_locale());
        foreach ($catalogue->get_domains() as $domain) {
            // extract intl-icu messages only
            $intl_domain = $domain . Message_Catalogue_Interface::INTL_DOMAIN_SUFFIX;
            if ($intl_messages = $catalogue->all($intl_domain)) {
                if (self::DESC === $sort) {
                    krsort($intl_messages);
                } elseif (self::ASC === $sort) {
                    ksort($intl_messages);
                }
                $sorted_catalogue->add($intl_messages, $intl_domain);
            }
            // extract all messages and subtract intl-icu messages
            if ($messages = array_diff($catalogue->all($domain), $intl_messages)) {
                if (self::DESC === $sort) {
                    krsort($messages);
                } elseif (self::ASC === $sort) {
                    ksort($messages);
                }
                $sorted_catalogue->add($messages, $domain);
            }
            if ($metadata = $catalogue->get_metadata('', $intl_domain)) {
                foreach ($metadata as $k => $v) {
                    $sorted_catalogue->set_metadata($k, $v, $intl_domain);
                }
            }
            if ($metadata = $catalogue->get_metadata('', $domain)) {
                foreach ($metadata as $k => $v) {
                    $sorted_catalogue->set_metadata($k, $v, $domain);
                }
            }
        }
        foreach ($catalogue->get_resources() as $resource) {
            $sorted_catalogue->add_resource($resource);
        }
        return $sorted_catalogue;
    }
    private function extract_messages(string $locale, array $trans_paths, string $prefix): Message_Catalogue
    {
        $extracted_catalogue = new Message_Catalogue($locale);
        $this->extractor->set_prefix($prefix);
        $trans_paths = $this->filter_duplicate_trans_paths($trans_paths);
        foreach ($trans_paths as $path) {
            if (is_dir($path) || is_file($path)) {
                $this->extractor->extract($path, $extracted_catalogue);
            }
        }
        return $extracted_catalogue;
    }
    private function filter_duplicate_trans_paths(array $trans_paths): array
    {
        $trans_paths = array_filter(array_map(realpath(...), $trans_paths));
        sort($trans_paths);
        $filtered_paths = [];
        foreach ($trans_paths as $path) {
            foreach ($filtered_paths as $filtered_path) {
                if (str_starts_with($path, $filtered_path . \DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }
            $filtered_paths[] = $path;
        }
        return $filtered_paths;
    }
    private function load_current_messages(string $locale, array $trans_paths): Message_Catalogue
    {
        $current_catalogue = new Message_Catalogue($locale);
        foreach ($trans_paths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $current_catalogue);
            }
        }
        return $current_catalogue;
    }
    private function get_root_trans_paths(): array
    {
        $trans_paths = $this->trans_paths;
        if ($this->default_trans_path) {
            $trans_paths[] = $this->default_trans_path;
        }
        return $trans_paths;
    }
    private function get_root_code_paths(Kernel_Interface $kernel): array
    {
        $code_paths = $this->code_paths;
        $code_paths[] = $kernel->get_project_dir() . '/src';
        if ($this->default_views_path) {
            $code_paths[] = $this->default_views_path;
        }
        return $code_paths;
    }
    private function remove_no_fill_translations(Message_Catalogue_Interface $operation): void
    {
        foreach ($operation->all('messages') as $key => $message) {
            if (str_starts_with((string) $message, self::NO_FILL_PREFIX)) {
                $operation->set($key, '', 'messages');
            }
        }
    }
}