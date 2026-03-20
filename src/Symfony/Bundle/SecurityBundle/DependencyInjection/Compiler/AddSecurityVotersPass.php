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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Priority_Tagged_Service_Trait;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Core\Authorization\Voter\Traceable_Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Voter_Interface;
/**
 * Adds all configured security voters to the access decision manager.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Add_Security_Voters_Pass implements Compiler_Pass_Interface
{
    use Priority_Tagged_Service_Trait;
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('security.access.decision_manager')) {
            return;
        }
        $voters = $this->find_and_sort_tagged_services(new Tagged_Iterator_Argument('security.voter'), $container);
        if (!$voters) {
            throw new LogicException('No security voters found. You need to tag at least one with "security.voter".');
        }
        $debug = $container->get_parameter('kernel.debug');
        $voter_services = [];
        foreach ($voters as $voter) {
            $voter_service_id = (string) $voter;
            $definition = $container->get_definition($voter_service_id);
            $class = $container->get_parameter_bag()->resolve_value($definition->get_class());
            if (!is_a($class, Voter_Interface::class, true)) {
                throw new LogicException(\sprintf('"%s" must implement the "%s" when used as a voter.', $class, Voter_Interface::class));
            }
            if ($debug) {
                $voter_services[] = new Reference($debug_voter_service_id = '.debug.security.voter.' . $voter_service_id);
                $container->register($debug_voter_service_id, Traceable_Voter::class)->add_argument($voter)->add_argument(new Reference('event_dispatcher'));
            } else {
                $voter_services[] = $voter;
            }
        }
        $container->get_definition('security.access.decision_manager')->replace_argument(0, new Iterator_Argument($voter_services));
    }
}