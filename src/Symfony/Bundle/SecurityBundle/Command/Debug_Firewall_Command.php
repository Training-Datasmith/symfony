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
namespace Symfony\Bundle\Security_Bundle\Command;

use Psr\Container\Container_Interface;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Context;
use Symfony\Bundle\Security_Bundle\Security\Lazy_Firewall_Context;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Security\Http\Authenticator\Authenticator_Interface;
use Symfony\Component\Security\Http\Authenticator\Debug\Traceable_Authenticator;
/**
 * @author Timo Bakx <timobakx@gmail.com>
 */
#[As_Command(name: 'debug:firewall', description: 'Display information about your security firewall(s)')]
final class Debug_Firewall_Command extends Command
{
    /**
     * @param string[]                   $firewallNames
     * @param AuthenticatorInterface[][] $authenticators
     */
    public function __construct(private array $firewall_names, private readonly Container_Interface $contexts, private readonly Container_Interface $event_dispatchers, private array $authenticators)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $example_name = $this->get_example_name();
        $this->set_help(<<<EOF
        The <info>%command.name%</info> command displays the firewalls that are configured
        in your application:
        
          <info>php %command.full_name%</info>
        
        You can pass a firewall name to display more detailed information about
        a specific firewall:
        
          <info>php %command.full_name% {$example_name}</info>
        
        To include all events and event listeners for a specific firewall, use the
        <info>events</info> option:
        
          <info>php %command.full_name% --events {$example_name}</info>
        
        EOF)->set_definition([new Input_Argument('name', Input_Argument::OPTIONAL, \sprintf('A firewall name (for example "%s")', $example_name)), new Input_Option('events', null, Input_Option::VALUE_NONE, 'Include a list of event listeners (only available in combination with the "name" argument)')]);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $name = $input->get_argument('name');
        if (null === $name) {
            $this->display_firewall_list($io);
            return 0;
        }
        $service_id = \sprintf('security.firewall.map.context.%s', $name);
        if (!$this->contexts->has($service_id)) {
            $io->error(\sprintf('Firewall %s was not found. Available firewalls are: %s', $name, implode(', ', $this->firewall_names)));
            return 1;
        }
        /** @var FirewallContext $context */
        $context = $this->contexts->get($service_id);
        $io->title(\sprintf('Firewall "%s"', $name));
        $this->display_firewall_summary($name, $context, $io);
        $this->display_switch_user($context, $io);
        if ($input->get_option('events')) {
            $this->display_event_listeners($name, $context, $io);
        }
        $this->display_authenticators($name, $io);
        return 0;
    }
    protected function display_firewall_list(Symfony_Style $io): void
    {
        $io->title('Firewalls');
        $io->text('The following firewalls are defined:');
        $io->listing($this->firewall_names);
        $io->comment(\sprintf('To view details of a specific firewall, re-run this command with a firewall name. (e.g. <comment>debug:firewall %s</comment>)', $this->get_example_name()));
    }
    protected function display_firewall_summary(string $name, Firewall_Context $context, Symfony_Style $io): void
    {
        if (null === $context->get_config()) {
            return;
        }
        $rows = [['Name', $name], ['Context', $context->get_config()->get_context()], ['Lazy', $context instanceof Lazy_Firewall_Context ? 'Yes' : 'No'], ['Stateless', $context->get_config()->is_stateless() ? 'Yes' : 'No'], ['User Checker', $context->get_config()->get_user_checker()], ['Provider', $context->get_config()->get_provider()], ['Entry Point', $context->get_config()->get_entry_point()], ['Access Denied URL', $context->get_config()->get_access_denied_url()], ['Access Denied Handler', $context->get_config()->get_access_denied_handler()]];
        $io->table(['Option', 'Value'], $rows);
    }
    private function display_switch_user(Firewall_Context $context, Symfony_Style $io): void
    {
        if (null === ($config = $context->get_config()) || null === $switch_user = $config->get_switch_user()) {
            return;
        }
        $io->section('User switching');
        $io->table(['Option', 'Value'], [['Parameter', $switch_user['parameter'] ?? ''], ['Provider', $switch_user['provider'] ?? $config->get_provider()], ['User Role', $switch_user['role'] ?? '']]);
    }
    protected function display_event_listeners(string $name, Firewall_Context $context, Symfony_Style $io): void
    {
        $io->title(\sprintf('Event listeners for firewall "%s"', $name));
        $dispatcher_id = \sprintf('security.event_dispatcher.%s', $name);
        if (!$this->event_dispatchers->has($dispatcher_id)) {
            $io->text('No event dispatcher has been registered for this firewall.');
            return;
        }
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->event_dispatchers->get($dispatcher_id);
        foreach ($dispatcher->get_listeners() as $event => $listeners) {
            $io->section(\sprintf('"%s" event', $event));
            $rows = [];
            foreach ($listeners as $order => $listener) {
                $rows[] = [\sprintf('#%d', $order + 1), $this->format_callable($listener), $dispatcher->get_listener_priority($event, $listener)];
            }
            $io->table(['Order', 'Callable', 'Priority'], $rows);
        }
    }
    private function display_authenticators(string $name, Symfony_Style $io): void
    {
        $io->title(\sprintf('Authenticators for firewall "%s"', $name));
        $authenticators = $this->authenticators[$name] ?? [];
        if (0 === \count($authenticators)) {
            $io->text('No authenticators have been registered for this firewall.');
            return;
        }
        $io->table(['Classname'], array_map(static fn($authenticator): array => [($authenticator instanceof Traceable_Authenticator ? $authenticator->get_authenticator() : $authenticator)::class], $authenticators));
    }
    private function format_callable(mixed $callable): string
    {
        if (\is_array($callable)) {
            if (\is_object($callable[0])) {
                return \sprintf('%s::%s()', $callable[0]::class, $callable[1]);
            }
            return \sprintf('%s::%s()', $callable[0], $callable[1]);
        }
        if (\is_string($callable)) {
            return \sprintf('%s()', $callable);
        }
        if ($callable instanceof \Closure) {
            $r = new \ReflectionFunction($callable);
            if ($r->is_anonymous()) {
                return 'Closure()';
            }
            if ($class = $r->get_closure_called_class()) {
                return \sprintf('%s::%s()', $class->name, $r->name);
            }
            return $r->name . '()';
        }
        if (method_exists($callable, '__invoke')) {
            return \sprintf('%s::__invoke()', $callable::class);
        }
        throw new \InvalidArgumentException('Callable is not describable.');
    }
    private function get_example_name(): string
    {
        $name = 'main';
        if (!\in_array($name, $this->firewall_names, true)) {
            return reset($this->firewall_names);
        }
        return $name;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('name')) {
            $suggestions->suggest_values($this->firewall_names);
        }
    }
}