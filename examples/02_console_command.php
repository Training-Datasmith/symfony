<?php

declare(strict_types=1);

/**
 * Example 2: Console Component — building a CLI application with commands
 *
 * Demonstrates creating a custom command and running it via Application.
 *
 * Usage:
 *   php examples/02_console_command.php greet --name=World --shout
 *   php examples/02_console_command.php list
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// ---------------------------------------------------------------------------
// Define a command using the #[AsCommand] attribute
// ---------------------------------------------------------------------------

#[AsCommand(name: 'greet', description: 'Greet someone from the command line')]
class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'name',
                mode: InputArgument::OPTIONAL,
                description: 'The name of the person to greet',
                default: 'World',
            )
            ->addOption(
                name: 'shout',
                shortcut: 's',
                mode: InputOption::VALUE_NONE,
                description: 'If set, the greeting will be output in uppercase',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = $input->getArgument('name');
        $greeting = "Hello, {$name}!";

        if ($input->getOption('shout')) {
            $greeting = strtoupper($greeting);
        }

        $io->success($greeting);

        return Command::SUCCESS;
    }
}

// ---------------------------------------------------------------------------
// Bootstrap the Application and register the command
// ---------------------------------------------------------------------------

$app = new Application('example-app', '1.0.0');
$app->add(new GreetCommand());

// Run the application; $argv comes from the CLI
$app->run();
