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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\Env_Parameter_Exception;
/**
 * This class is used to remove circular dependencies between individual passes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Compiler
{
    private readonly Pass_Config $pass_config;
    private array $log = [];
    private readonly Service_Reference_Graph $service_reference_graph;
    public function __construct()
    {
        $this->pass_config = new Pass_Config();
        $this->service_reference_graph = new Service_Reference_Graph();
    }
    public function get_pass_config(): Pass_Config
    {
        return $this->pass_config;
    }
    public function get_service_reference_graph(): Service_Reference_Graph
    {
        return $this->service_reference_graph;
    }
    public function add_pass(Compiler_Pass_Interface $pass, string $type = Pass_Config::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): void
    {
        $this->pass_config->add_pass($pass, $type, $priority);
    }
    /**
     * @final
     */
    public function log(Compiler_Pass_Interface $pass, string $message): void
    {
        if (str_contains($message, "\n")) {
            $message = str_replace("\n", "\n" . $pass::class . ': ', trim($message));
        }
        $this->log[] = $pass::class . ': ' . $message;
    }
    public function get_log(): array
    {
        return $this->log;
    }
    /**
     * Run the Compiler and process all Passes.
     */
    public function compile(Container_Builder $container): void
    {
        try {
            foreach ($this->pass_config->get_passes() as $pass) {
                $pass->process($container);
            }
        } catch (\Exception $e) {
            $used_envs = [];
            $prev = $e;
            do {
                $msg = $prev->get_message();
                if ($msg !== $resolved_msg = $container->resolve_env_placeholders($msg, null, $used_envs)) {
                    $r = new \ReflectionProperty($prev, 'message');
                    $r->set_value($prev, $resolved_msg);
                }
            } while ($prev = $prev->get_previous());
            if ($used_envs) {
                $e = new Env_Parameter_Exception($used_envs, $e);
            }
            throw $e;
        } finally {
            $this->get_service_reference_graph()->clear();
        }
    }
}