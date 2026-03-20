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
use Symfony\Component\Translation\Data_Collector_Translator;
use Symfony\Component\Translation\Extractor\Extractor_Interface;
use Symfony\Component\Translation\Logging_Translator;
use Symfony\Component\Translation\Message_Catalogue;
use Symfony\Component\Translation\Reader\Translation_Reader_Interface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * Helps finding unused or missing translation messages in a given locale
 * and comparing them with the fallback ones.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 *
 * @final
 */
#[As_Command(name: 'debug:translation', description: 'Display translation messages information')]
class Translation_Debug_Command extends Command
{
    public const EXIT_CODE_GENERAL_ERROR = 64;
    public const EXIT_CODE_MISSING = 65;
    public const EXIT_CODE_UNUSED = 66;
    public const EXIT_CODE_FALLBACK = 68;
    public const MESSAGE_MISSING = 0;
    public const MESSAGE_UNUSED = 1;
    public const MESSAGE_EQUALS_FALLBACK = 2;
    public function __construct(private readonly Translator_Interface $translator, private readonly Translation_Reader_Interface $reader, private readonly Extractor_Interface $extractor, private readonly ?string $default_trans_path = null, private readonly ?string $default_views_path = null, private readonly array $trans_paths = [], private readonly array $code_paths = [], private array $enabled_locales = [])
    {
        $this->enabled_locales = array_filter($enabled_locales);
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('locale', Input_Argument::REQUIRED, 'The locale'), new Input_Argument('bundle', Input_Argument::OPTIONAL, 'The bundle name or directory where to load the messages'), new Input_Option('domain', null, Input_Option::VALUE_REQUIRED, 'The messages domain'), new Input_Option('only-missing', null, Input_Option::VALUE_NONE, 'Display only missing messages'), new Input_Option('only-unused', null, Input_Option::VALUE_NONE, 'Display only unused messages'), new Input_Option('all', null, Input_Option::VALUE_NONE, 'Load messages from all registered bundles')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command helps finding unused or missing translation
        messages and comparing them with the fallback ones by inspecting the
        templates and translation files of a given bundle or the default translations directory.
        
        You can display information about bundle translations in a specific locale:
        
          <info>php %command.full_name% en AcmeDemoBundle</info>
        
        You can also specify a translation domain for the search:
        
          <info>php %command.full_name% --domain=messages en AcmeDemoBundle</info>
        
        You can only display missing messages:
        
          <info>php %command.full_name% --only-missing en AcmeDemoBundle</info>
        
        You can only display unused messages:
        
          <info>php %command.full_name% --only-unused en AcmeDemoBundle</info>
        
        You can display information about application translations in a specific locale:
        
          <info>php %command.full_name% en</info>
        
        You can display information about translations in all registered bundles in a specific locale:
        
          <info>php %command.full_name% --all en</info>
        
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $locale = $input->get_argument('locale');
        $domain = $input->get_option('domain');
        $exit_code = self::SUCCESS;
        /** @var KernelInterface $kernel */
        $kernel = $this->get_application()->get_kernel();
        // Define Root Paths
        $trans_paths = $this->get_root_trans_paths();
        $code_paths = $this->get_root_code_paths($kernel);
        // Override with provided Bundle info
        if (null !== $input->get_argument('bundle')) {
            try {
                $bundle = $kernel->get_bundle($input->get_argument('bundle'));
                $bundle_dir = $bundle->get_path();
                $trans_paths = [is_dir($bundle_dir . '/Resources/translations') ? $bundle_dir . '/Resources/translations' : $bundle_dir . '/translations'];
                $code_paths = [is_dir($bundle_dir . '/Resources/views') ? $bundle_dir . '/Resources/views' : $bundle_dir . '/templates'];
                if ($this->default_trans_path) {
                    $trans_paths[] = $this->default_trans_path;
                }
                if ($this->default_views_path) {
                    $code_paths[] = $this->default_views_path;
                }
            } catch (\InvalidArgumentException) {
                // such a bundle does not exist, so treat the argument as path
                $path = $input->get_argument('bundle');
                $trans_paths = [$path . '/translations'];
                $code_paths = [$path . '/templates'];
                if (!is_dir($trans_paths[0])) {
                    throw new InvalidArgumentException(\sprintf('"%s" is neither an enabled bundle nor a directory.', $trans_paths[0]));
                }
            }
        } elseif ($input->get_option('all')) {
            foreach ($kernel->get_bundles() as $bundle) {
                $bundle_dir = $bundle->get_path();
                $trans_paths[] = is_dir($bundle_dir . '/Resources/translations') ? $bundle_dir . '/Resources/translations' : $bundle->get_path() . '/translations';
                $code_paths[] = is_dir($bundle_dir . '/Resources/views') ? $bundle_dir . '/Resources/views' : $bundle->get_path() . '/templates';
            }
        }
        // Extract used messages
        $extracted_catalogue = $this->extract_messages($locale, $code_paths);
        // Load defined messages
        $current_catalogue = $this->load_current_messages($locale, $trans_paths);
        // Merge defined and extracted messages to get all message ids
        $merge_operation = new Merge_Operation($extracted_catalogue, $current_catalogue);
        $all_messages = $merge_operation->get_result()->all($domain);
        if (null !== $domain) {
            $all_messages = [$domain => $all_messages];
        }
        // No defined or extracted messages
        if (!$all_messages || null !== $domain && empty($all_messages[$domain])) {
            $output_message = \sprintf('No defined or extracted messages for locale "%s"', $locale);
            if (null !== $domain) {
                $output_message .= \sprintf(' and domain "%s"', $domain);
            }
            $io->get_error_style()->warning($output_message);
            return self::EXIT_CODE_GENERAL_ERROR;
        }
        // Load the fallback catalogues
        $fallback_catalogues = $this->load_fallback_catalogues($locale, $trans_paths);
        // Display header line
        $headers = ['State', 'Domain', 'Id', \sprintf('Message Preview (%s)', $locale)];
        foreach ($fallback_catalogues as $fallback_catalogue) {
            $headers[] = \sprintf('Fallback Message Preview (%s)', $fallback_catalogue->get_locale());
        }
        $rows = [];
        // Iterate all message ids and determine their state
        foreach ($all_messages as $domain => $messages) {
            foreach (array_keys($messages) as $message_id) {
                $value = $current_catalogue->get($message_id, $domain);
                $states = [];
                if ($extracted_catalogue->defines($message_id, $domain)) {
                    if (!$current_catalogue->defines($message_id, $domain)) {
                        $states[] = self::MESSAGE_MISSING;
                        if (!$input->get_option('only-unused')) {
                            $exit_code |= self::EXIT_CODE_MISSING;
                        }
                    }
                } elseif ($current_catalogue->defines($message_id, $domain)) {
                    $states[] = self::MESSAGE_UNUSED;
                    if (!$input->get_option('only-missing')) {
                        $exit_code |= self::EXIT_CODE_UNUSED;
                    }
                }
                if (!\in_array(self::MESSAGE_UNUSED, $states, true) && $input->get_option('only-unused')) {
                    continue;
                }
                if (!\in_array(self::MESSAGE_MISSING, $states, true) && $input->get_option('only-missing')) {
                    continue;
                }
                foreach ($fallback_catalogues as $fallback_catalogue) {
                    if ($fallback_catalogue->defines($message_id, $domain) && $value === $fallback_catalogue->get($message_id, $domain)) {
                        $states[] = self::MESSAGE_EQUALS_FALLBACK;
                        $exit_code |= self::EXIT_CODE_FALLBACK;
                        break;
                    }
                }
                $row = [$this->format_states($states), $domain, $this->format_id($message_id), $this->sanitize_string($value)];
                foreach ($fallback_catalogues as $fallback_catalogue) {
                    $row[] = $this->sanitize_string($fallback_catalogue->get($message_id, $domain));
                }
                $rows[] = $row;
            }
        }
        $io->table($headers, $rows);
        return $exit_code;
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
            $available_bundles = [];
            foreach ($kernel->get_bundles() as $bundle) {
                $available_bundles[] = $bundle->get_name();
                if ($extension = $bundle->get_container_extension()) {
                    $available_bundles[] = $extension->get_alias();
                }
            }
            $suggestions->suggest_values($available_bundles);
            return;
        }
        if ($input->must_suggest_option_values_for('domain')) {
            $locale = $input->get_argument('locale');
            $merge_operation = new Merge_Operation($this->extract_messages($locale, $this->get_root_code_paths($kernel)), $this->load_current_messages($locale, $this->get_root_trans_paths()));
            $suggestions->suggest_values($merge_operation->get_domains());
        }
    }
    private function format_state(int $state): string
    {
        if (self::MESSAGE_MISSING === $state) {
            return '<error> missing </error>';
        }
        if (self::MESSAGE_UNUSED === $state) {
            return '<comment> unused </comment>';
        }
        if (self::MESSAGE_EQUALS_FALLBACK === $state) {
            return '<info> fallback </info>';
        }
        return $state;
    }
    private function format_states(array $states): string
    {
        $result = [];
        foreach ($states as $state) {
            $result[] = $this->format_state($state);
        }
        return implode(' ', $result);
    }
    private function format_id(string $id): string
    {
        return \sprintf('<fg=cyan;options=bold>%s</>', $id);
    }
    private function sanitize_string(string $string, int $length = 40): string
    {
        $string = trim((string) preg_replace('/\s+/', ' ', $string));
        if (false !== $encoding = mb_detect_encoding($string, null, true)) {
            if (mb_strlen($string, $encoding) > $length) {
                return mb_substr($string, 0, $length - 3, $encoding) . '...';
            }
        } elseif (\strlen($string) > $length) {
            return substr($string, 0, $length - 3) . '...';
        }
        return $string;
    }
    private function extract_messages(string $locale, array $trans_paths): Message_Catalogue
    {
        $extracted_catalogue = new Message_Catalogue($locale);
        foreach ($trans_paths as $path) {
            if (is_dir($path) || is_file($path)) {
                $this->extractor->extract($path, $extracted_catalogue);
            }
        }
        return $extracted_catalogue;
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
    /**
     * @return MessageCatalogue[]
     */
    private function load_fallback_catalogues(string $locale, array $trans_paths): array
    {
        $fallback_catalogues = [];
        if ($this->translator instanceof Translator || $this->translator instanceof Data_Collector_Translator || $this->translator instanceof Logging_Translator) {
            foreach ($this->translator->get_fallback_locales() as $fallback_locale) {
                if ($fallback_locale === $locale) {
                    continue;
                }
                $fallback_catalogue = new Message_Catalogue($fallback_locale);
                foreach ($trans_paths as $path) {
                    if (is_dir($path)) {
                        $this->reader->read($path, $fallback_catalogue);
                    }
                }
                $fallback_catalogues[] = $fallback_catalogue;
            }
        }
        return $fallback_catalogues;
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
}