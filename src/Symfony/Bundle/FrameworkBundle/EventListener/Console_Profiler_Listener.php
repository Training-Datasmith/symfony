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
namespace Symfony\Bundle\Framework_Bundle\Event_Listener;

use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Debug\Cli_Request;
use Symfony\Component\Console\Event\Console_Command_Event;
use Symfony\Component\Console\Event\Console_Error_Event;
use Symfony\Component\Console\Event\Console_Terminate_Event;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Profiler\Profile;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Console_Profiler_Listener implements Event_Subscriber_Interface
{
    private ?\Throwable $error = null;
    /** @var \SplObjectStorage<Request, Profile> */
    private \Spl_Object_Storage $profiles;
    /** @var \SplObjectStorage<Request, ?Request> */
    private \Spl_Object_Storage $parents;
    private bool $disabled = false;
    public function __construct(private readonly Profiler $profiler, private readonly Request_Stack $request_stack, private readonly Stopwatch $stopwatch, private readonly bool $cli_mode, private readonly ?Url_Generator_Interface $url_generator = null)
    {
        $this->profiles = new \Spl_Object_Storage();
        $this->parents = new \Spl_Object_Storage();
    }
    public static function get_subscribed_events(): array
    {
        return [Console_Events::COMMAND => ['initialize', 4096], Console_Events::ERROR => ['catch', -2048], Console_Events::TERMINATE => ['profile', -4096]];
    }
    public function initialize(Console_Command_Event $event): void
    {
        if (!$this->cli_mode) {
            return;
        }
        $input = $event->get_input();
        if (!$input->has_option('profile') || !$input->get_option('profile')) {
            $this->disabled = true;
            return;
        }
        $request = $this->request_stack->get_current_request();
        if (!$request instanceof Cli_Request || $request->command !== $event->get_command()) {
            return;
        }
        $request->attributes->set('_stopwatch_token', bin2hex(random_bytes(3)));
        $this->stopwatch->open_section();
    }
    public function catch(Console_Error_Event $event): void
    {
        if (!$this->cli_mode) {
            return;
        }
        $this->error = $event->get_error();
    }
    public function profile(Console_Terminate_Event $event): void
    {
        $error = $this->error;
        $this->error = null;
        if (!$this->cli_mode || $this->disabled) {
            $this->disabled = false;
            return;
        }
        $request = $this->request_stack->get_current_request();
        if (!$request instanceof Cli_Request || $request->command !== $event->get_command()) {
            return;
        }
        if (!$this->profiler->is_enabled()) {
            return;
        }
        if (null !== $section_id = $request->attributes->get('_stopwatch_token')) {
            // we must close the section before saving the profile to allow late collect
            try {
                $this->stopwatch->stop_section($section_id);
            } catch (\LogicException) {
                // noop
            }
        }
        $request->command->exit_code = $event->get_exit_code();
        $request->command->interrupted_by_signal = $event->get_interrupting_signal();
        $profile = $this->profiler->collect($request, $request->get_response(), $error);
        $this->profiles[$request] = $profile;
        if ($this->parents[$request] = $this->request_stack->get_parent_request()) {
            // do not save on sub commands
            return;
        }
        // attach children to parents
        foreach ($this->profiles as $request) {
            if (null === $parent_request = $this->parents[$request]) {
                continue;
            }
            if (!isset($this->profiles[$parent_request])) {
                continue;
            }
            $this->profiles[$parent_request]->add_child($this->profiles[$request]);
        }
        $output = $event->get_output();
        $output = $output instanceof Console_Output_Interface && $output->is_verbose() ? $output->get_error_output() : null;
        // save profiles
        foreach ($this->profiles as $r) {
            $p = $this->profiles[$r];
            $this->profiler->save_profile($p);
            if ($this->url_generator && $output) {
                $token = $p->get_token();
                $output->writeln(\sprintf('See profile <href=%s>%s</>', $this->url_generator->generate('_profiler', ['token' => $token], Url_Generator_Interface::ABSOLUTE_URL), $token));
            }
        }
        $this->profiles = new \Spl_Object_Storage();
        $this->parents = new \Spl_Object_Storage();
    }
}