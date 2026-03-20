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
namespace Symfony\Component\Console\Debug;

use Symfony\Component\Console\Command\Traceable_Command;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 * @internal
 */
final class Cli_Request extends Request
{
    public function __construct(public readonly Traceable_Command $command)
    {
        parent::__construct(attributes: ['_controller' => $command->command::class, '_virtual_type' => 'command'], server: $_SERVER);
    }
    // Methods below allow to populate a profile, thus enable search and filtering
    public function get_uri(): string
    {
        if ($this->server->has('SYMFONY_CLI_BINARY_NAME')) {
            $binary = $this->server->get('SYMFONY_CLI_BINARY_NAME') . ' console';
        } else {
            $binary = $this->server->get('argv')[0];
        }
        return $binary . ' ' . $this->command->input;
    }
    public function get_method(): string
    {
        return $this->command->is_interactive ? 'INTERACTIVE' : 'BATCH';
    }
    public function get_response(): Response
    {
        return new class($this->command->exit_code) extends Response
        {
            public function __construct(private readonly int $exit_code)
            {
                parent::__construct();
            }
            public function get_status_code(): int
            {
                return $this->exit_code;
            }
        };
    }
    public function get_client_ip(): string
    {
        $application = $this->command->get_application();
        return $application->get_name() . ' ' . $application->get_version();
    }
}