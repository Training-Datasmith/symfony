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

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Uses the session domain to restrict allowed redirection targets.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Add_Session_Domain_Constraint_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_parameter('session.storage.options') || !$container->has('security.http_utils')) {
            return;
        }
        $session_options = $container->get_parameter('session.storage.options');
        $domain_regexp = empty($session_options['cookie_domain']) ? '%%s' : \sprintf('(?:%%%%s|(?:.+\.)?%s)', preg_quote(trim((string) $session_options['cookie_domain'], '.')));
        if ('auto' === ($session_options['cookie_secure'] ?? null)) {
            $secure_domain_regexp = \sprintf('{^https://%s$}i', $domain_regexp);
            $domain_regexp = 'https?://' . $domain_regexp;
        } else {
            $secure_domain_regexp = null;
            $domain_regexp = (empty($session_options['cookie_secure']) ? 'https?://' : 'https://') . $domain_regexp;
        }
        $container->get_definition('security.http_utils')->add_argument(\sprintf('{^%s$}i', $domain_regexp))->add_argument($secure_domain_regexp);
    }
}