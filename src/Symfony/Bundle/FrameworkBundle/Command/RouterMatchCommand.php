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
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Expression_Language\Expression_Function_Provider_Interface;
use Symfony\Component\Routing\Matcher\Traceable_Url_Matcher;
use Symfony\Component\Routing\Router_Interface;
/**
 * A console command to test route matching.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
#[As_Command(name: 'router:match', description: 'Help debug routes by simulating a path info match')]
class Router_Match_Command extends Command
{
    /**
     * @param iterable<mixed, ExpressionFunctionProviderInterface> $expressionLanguageProviders
     */
    public function __construct(private readonly Router_Interface $router, private readonly iterable $expression_language_providers = [])
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('path_info', Input_Argument::REQUIRED, 'A path info'), new Input_Option('method', null, Input_Option::VALUE_REQUIRED, 'Set the HTTP method'), new Input_Option('scheme', null, Input_Option::VALUE_REQUIRED, 'Set the URI scheme (usually http or https)'), new Input_Option('host', null, Input_Option::VALUE_REQUIRED, 'Set the URI host')])->set_help(<<<'EOF'
        The <info>%command.name%</info> shows which routes match a given request and which don't and for what reason:
        
          <info>php %command.full_name% /foo</info>
        
        or
        
          <info>php %command.full_name% /foo --method POST --scheme https --host symfony.com --verbose</info>
        
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $context = $this->router->get_context();
        if (null !== $method = $input->get_option('method')) {
            $context->set_method($method);
        }
        if (null !== $scheme = $input->get_option('scheme')) {
            $context->set_scheme($scheme);
        }
        if (null !== $host = $input->get_option('host')) {
            $context->set_host($host);
        }
        $matcher = new Traceable_Url_Matcher($this->router->get_route_collection(), $context);
        foreach ($this->expression_language_providers as $provider) {
            $matcher->add_expression_language_provider($provider);
        }
        $traces = $matcher->get_traces($input->get_argument('path_info'));
        $io->new_line();
        $matches = false;
        foreach ($traces as $trace) {
            if (Traceable_Url_Matcher::ROUTE_ALMOST_MATCHES == $trace['level']) {
                $io->text(\sprintf('Route <info>"%s"</> almost matches but %s', $trace['name'], lcfirst((string) $trace['log'])));
            } elseif (Traceable_Url_Matcher::ROUTE_MATCHES == $trace['level']) {
                $io->success(\sprintf('Route "%s" matches', $trace['name']));
                $router_debug_command = $this->get_application()->find('debug:router');
                $router_debug_command->run(new Array_Input(['name' => $trace['name']]), $output);
                $matches = true;
            } elseif ($input->get_option('verbose')) {
                $io->text(\sprintf('Route "%s" does not match: %s', $trace['name'], $trace['log']));
            }
        }
        if (!$matches) {
            $io->error(\sprintf('None of the routes match the path "%s"', $input->get_argument('path_info')));
            return 1;
        }
        return 0;
    }
}