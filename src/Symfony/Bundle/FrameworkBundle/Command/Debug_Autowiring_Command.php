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

use Symfony\Bundle\Framework_Bundle\Console\Descriptor\Descriptor;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
/**
 * A console command for autowiring information.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
#[As_Command(name: 'debug:autowiring', description: 'List classes/interfaces you can use for autowiring')]
class Debug_Autowiring_Command extends Container_Debug_Command
{
    public function __construct(?string $name = null, private readonly ?File_Link_Formatter $file_link_formatter = null)
    {
        parent::__construct($name);
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('search', Input_Argument::OPTIONAL, 'A search filter'), new Input_Option('all', null, Input_Option::VALUE_NONE, 'Show also services that are not aliased')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command displays the classes and interfaces that
        you can use as type-hints for autowiring:
        
          <info>php %command.full_name%</info>
        
        You can also pass a search term to filter the list:
        
          <info>php %command.full_name% log</info>
        
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $error_io = $io->get_error_style();
        $container = $this->get_container_builder($this->get_application()->get_kernel());
        $service_ids = $container->get_service_ids();
        $service_ids = array_filter($service_ids, $this->filter_to_service_types(...));
        if ($search = $input->get_argument('search')) {
            $search_normalized = preg_replace('/[^a-zA-Z0-9\x7f-\xff $]++/', '', (string) $search);
            $service_ids = array_filter($service_ids, static fn(string $service_id): bool => false !== stripos(str_replace('\\', '', $service_id), (string) $search_normalized) && !str_starts_with($service_id, '.'));
            if (!$service_ids) {
                $error_io->error(\sprintf('No autowirable classes or interfaces found matching "%s"', $search));
                return 1;
            }
        }
        $reverse_aliases = [];
        foreach ($container->get_aliases() as $id => $alias) {
            if ('.' === ($id[0] ?? null)) {
                $reverse_aliases[(string) $alias][] = $id;
            }
        }
        uasort($service_ids, strnatcmp(...));
        $io->title('Autowirable Types');
        $io->text('Use the following classes & interfaces as type-hints in constructor arguments to autowire services.');
        $io->text('Add <fg=magenta>#[Target(\'</><fg=cyan>name</><fg=magenta>\')]</> to the argument to select a specific variant.');
        if ($search) {
            $io->text(\sprintf('(only showing classes/interfaces matching <comment>%s</comment>)', $search));
        }
        $has_alias = [];
        $all = $input->get_option('all');
        $previous_id = '-';
        $service_ids_nb = 0;
        foreach ($service_ids as $service_id) {
            if ($container->has_definition($service_id) && $container->get_definition($service_id)->has_tag('container.excluded')) {
                continue;
            }
            $text = [];
            $resolved_service_id = $service_id;
            $description = '';
            if ($is_new_group = !str_starts_with($service_id, $previous_id . ' $')) {
                $text[] = '';
                $previous_id = preg_replace('/ \$.*/', '', $service_id);
                $description = Descriptor::get_class_description($previous_id, $resolved_service_id);
                if ('' !== $description && isset($has_alias[$previous_id])) {
                    continue;
                }
            }
            if ($container->has_alias($service_id)) {
                $has_alias[$service_id] = true;
                $service_alias = $container->get_alias($service_id);
                $alias = (string) $service_alias;
                $target = null;
                foreach ($reverse_aliases[$alias] ?? [] as $id) {
                    if (!str_starts_with($id, '.' . $previous_id . ' $')) {
                        continue;
                    }
                    if (!str_contains($service_id, ' $')) {
                        continue;
                    }
                    $target = substr($id, \strlen((string) $previous_id) + 3);
                    if ($container->find_definition($id) === $container->find_definition($service_id)) {
                        break;
                    }
                }
                if ($container->has_definition($service_alias) && $decorated = $container->get_definition($service_alias)->get_tag('container.decorator')) {
                    $alias = $decorated[0]['id'];
                }
                if ($is_new_group) {
                    // Build the main type line with optional file link
                    $type_line = \sprintf('<fg=yellow>%s</>', $previous_id);
                    if ('' !== $file_link = $this->get_file_link($previous_id)) {
                        $type_line = \sprintf('<fg=yellow;href=%s>%s</>', $file_link, $previous_id);
                    }
                    if (null !== $target) {
                        // Type whose first entry is already targeted (no un-targeted base)
                        $text[] = $type_line;
                        if ('' !== $description) {
                            $text[] = \sprintf('  %s', $description);
                        }
                        $target_line = \sprintf('  <fg=magenta>#[Target(\'</><fg=cyan>%s</><fg=magenta>\')]</>', $target);
                        if ($alias !== $target) {
                            $target_line .= \sprintf(' → <fg=cyan>%s</>', $alias);
                        }
                        if ($service_alias->is_deprecated()) {
                            $target_line .= ' <fg=magenta>[deprecated]</>';
                        }
                        $text[] = $target_line;
                    } else {
                        // Regular main entry: Type → alias
                        if ($alias !== $target) {
                            $type_line .= \sprintf(' → <fg=cyan>%s</>', $alias);
                        }
                        if ($service_alias->is_deprecated()) {
                            $type_line .= ' <fg=magenta>[deprecated]</>';
                        }
                        $text[] = $type_line;
                        if ('' !== $description) {
                            $text[] = \sprintf('  %s', $description);
                        }
                    }
                } else {
                    // Variant entry: indented #[Target] line
                    if (null !== $target) {
                        $variant_line = \sprintf('  <fg=magenta>#[Target(\'</><fg=cyan>%s</><fg=magenta>\')]</>', $target);
                    } else {
                        $variant_line = \sprintf('  <fg=yellow>%s</>', $service_id);
                    }
                    if ($alias !== $target) {
                        $variant_line .= \sprintf(' → <fg=cyan>%s</>', $alias);
                    }
                    if ($service_alias->is_deprecated()) {
                        $variant_line .= ' <fg=magenta>[deprecated]</>';
                    }
                    $text[] = $variant_line;
                }
            } elseif (!$all) {
                ++$service_ids_nb;
                continue;
            } else {
                // Service without alias (shown with --all)
                $service_line = \sprintf('<fg=yellow>%s</>', $previous_id);
                if ('' !== $file_link = $this->get_file_link($previous_id)) {
                    $service_line = \sprintf('<fg=yellow;href=%s>%s</>', $file_link, $previous_id);
                }
                if ($container->get_definition($service_id)->is_deprecated()) {
                    $service_line .= ' <fg=magenta>[deprecated]</>';
                }
                $text[] = $service_line;
                if ($is_new_group && '' !== $description) {
                    $text[] = \sprintf('  %s', $description);
                }
            }
            $io->text($text);
        }
        $io->new_line();
        if (0 < $service_ids_nb) {
            $io->text(\sprintf('%s more concrete service%s would be displayed when adding the "--all" option.', $service_ids_nb, $service_ids_nb > 1 ? 's' : ''));
        }
        if ($all) {
            $io->text('Pro-tip: use interfaces in your type-hints instead of classes to benefit from the dependency inversion principle.');
        }
        $io->new_line();
        return 0;
    }
    private function get_file_link(string $class): string
    {
        if (null === $this->file_link_formatter || null === $r = $this->get_container_builder($this->get_application()->get_kernel())->get_reflection_class($class, false)) {
            return '';
        }
        return $r->get_file_name() ? $this->file_link_formatter->format($r->get_file_name(), $r->get_start_line()) ?: '' : '';
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('search')) {
            $container = $this->get_container_builder($this->get_application()->get_kernel());
            $suggestions->suggest_values(array_filter($container->get_service_ids(), $this->filter_to_service_types(...)));
        }
    }
}