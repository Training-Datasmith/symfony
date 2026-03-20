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
namespace Symfony\Bundle\Debug_Bundle\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Var_Dumper\Command\Server_Dump_Command;
use Symfony\Component\Var_Dumper\Server\Dump_Server;
/**
 * A placeholder command easing VarDumper server discovery.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @internal
 */
#[As_Command(name: 'server:dump', description: 'Start a dump server that collects and displays dumps in a single place')]
class Server_Dump_Placeholder_Command extends Command
{
    private readonly Server_Dump_Command $replaced_command;
    public function __construct(?Dump_Server $server = null, array $descriptors = [])
    {
        $this->replaced_command = new Server_Dump_Command((new \ReflectionClass(Dump_Server::class))->new_instance_without_constructor(), $descriptors);
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition($this->replaced_command->get_definition());
        $this->set_help($this->replaced_command->get_help());
        $this->set_description($this->replaced_command->get_description());
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        (new Symfony_Style($input, $output))->get_error_style()->warning('In order to use the VarDumper server, set the "debug.dump_destination" config option to "tcp://%env(VAR_DUMPER_SERVER)%"');
        return 8;
    }
}