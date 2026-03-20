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

use Symfony\Bundle\Framework_Bundle\Console\Helper\Descriptor_Helper;
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
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Routing\Route_Collection;
use Symfony\Component\Routing\Router_Interface;
/**
 * A console command for retrieving information about routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @final
 */
#[As_Command(name: 'debug:router', description: 'Display current routes for an application')]
class Router_Debug_Command extends Command
{
    use Build_Debug_Container_Trait;
    public function __construct(private Router_Interface $router, private ?File_Link_Formatter $file_link_formatter = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('name', Input_Argument::OPTIONAL, 'A route name'), new Input_Option('show-controllers', null, Input_Option::VALUE_NONE, 'Show assigned controllers in overview'), new Input_Option('show-aliases', null, Input_Option::VALUE_NONE, 'Show aliases in overview'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), 'txt'), new Input_Option('raw', null, Input_Option::VALUE_NONE, 'To output raw route(s)'), new Input_Option('method', null, Input_Option::VALUE_REQUIRED, 'Filter by HTTP method', '', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])])->set_help(<<<'EOF'
        The <info>%command.name%</info> displays the configured routes:
        
          <info>php %command.full_name%</info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% --format=json</info>
        EOF);
    }
    /**
     * @throws InvalidArgumentException When route does not exist
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $name = $input->get_argument('name');
        $method = strtoupper((string) $input->get_option('method'));
        $helper = new Descriptor_Helper($this->file_link_formatter);
        $routes = $this->router->get_route_collection();
        $container = null;
        if ($this->file_link_formatter) {
            $container = fn(): \Symfony\Component\Dependency_Injection\Container_Builder => $this->get_container_builder($this->get_application()->get_kernel());
        }
        if ($name) {
            $route = $routes->get($name);
            $matching_routes = $this->find_route_name_containing($name, $routes, $method);
            if (!$input->is_interactive() && !$route && \count($matching_routes) > 1) {
                $helper->describe($io, $this->find_route_containing($name, $routes), ['format' => $input->get_option('format'), 'raw_text' => $input->get_option('raw'), 'show_controllers' => $input->get_option('show-controllers'), 'show_aliases' => $input->get_option('show-aliases'), 'output' => $io, 'method' => $method]);
                return 0;
            }
            if (!$route && $matching_routes) {
                $default = 1 === \count($matching_routes) ? $matching_routes[0] : null;
                $name = $io->choice('Select one of the matching routes', $matching_routes, $default);
                $route = $routes->get($name);
            }
            if (!$route) {
                throw new InvalidArgumentException(\sprintf('The route "%s" does not exist.', $name));
            }
            $helper->describe($io, $route, ['format' => $input->get_option('format'), 'raw_text' => $input->get_option('raw'), 'name' => $name, 'output' => $io, 'container' => $container]);
        } else {
            $helper->describe($io, $routes, ['format' => $input->get_option('format'), 'raw_text' => $input->get_option('raw'), 'show_controllers' => $input->get_option('show-controllers'), 'show_aliases' => $input->get_option('show-aliases'), 'output' => $io, 'container' => $container, 'method' => $method]);
        }
        return 0;
    }
    private function find_route_name_containing(string $name, Route_Collection $routes, string $method): array
    {
        $found_routes_names = [];
        foreach ($routes as $route_name => $route) {
            if (false !== stripos($route_name, $name) && (!$method || !$route->get_methods() || \in_array($method, $route->get_methods(), true))) {
                $found_routes_names[] = $route_name;
            }
        }
        return $found_routes_names;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('name')) {
            $suggestions->suggest_values(array_keys($this->router->get_route_collection()->all()));
            return;
        }
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    private function find_route_containing(string $name, Route_Collection $routes): Route_Collection
    {
        $found_routes = new Route_Collection();
        foreach ($routes as $route_name => $route) {
            if (false !== stripos($route_name, $name)) {
                $found_routes->add($route_name, $route);
            }
        }
        return $found_routes;
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return (new Descriptor_Helper())->get_formats();
    }
}