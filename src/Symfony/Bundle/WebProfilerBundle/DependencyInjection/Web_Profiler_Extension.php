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
namespace Symfony\Bundle\Web_Profiler_Bundle\Dependency_Injection;

use Symfony\Bundle\Web_Profiler_Bundle\Event_Listener\Web_Debug_Toolbar_Listener;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * WebProfilerExtension.
 *
 * Usage:
 *
 *     <webprofiler:config
 *        toolbar="true"
 *        intercept-redirects="true"
 *     />
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Web_Profiler_Extension extends Extension
{
    /**
     * Loads the web profiler configuration.
     *
     * @param array $configs An array of configuration settings
     */
    public function load(array $configs, Container_Builder $container): void
    {
        $configuration = $this->get_configuration($configs, $container);
        $config = $this->process_configuration($configuration, $configs);
        $loader = new Php_File_Loader($container, new File_Locator(__DIR__ . '/../Resources/config'));
        $loader->load('profiler.php');
        if ($config['toolbar']['enabled'] || $config['intercept_redirects']) {
            $loader->load('toolbar.php');
            $container->get_definition('web_profiler.debug_toolbar')->replace_argument(4, $config['excluded_ajax_paths']);
            $container->get_definition('web_profiler.debug_toolbar')->replace_argument(7, $config['toolbar']['ajax_replace']);
            $container->set_parameter('web_profiler.debug_toolbar.intercept_redirects', $config['intercept_redirects']);
            $container->set_parameter('web_profiler.debug_toolbar.mode', $config['toolbar']['enabled'] ? Web_Debug_Toolbar_Listener::ENABLED : Web_Debug_Toolbar_Listener::DISABLED);
        }
        $container->get_definition('debug.file_link_formatter')->replace_argument(3, new Service_Closure_Argument(new Reference('debug.file_link_formatter.url_format')));
    }
}