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
namespace Symfony\Bundle\Web_Profiler_Bundle\Profiler;

use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Profiler\Profile;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
use Twig\Environment;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Artur Wielogórski <wodor@wodor.net>
 *
 * @internal
 */
class Template_Manager
{
    public function __construct(protected Profiler $profiler, protected Environment $twig, protected array $templates)
    {
    }
    /**
     * Gets the template name for a given panel.
     *
     * @throws NotFoundHttpException
     */
    public function get_name(Profile $profile, string $panel): mixed
    {
        $templates = $this->get_names($profile);
        if (!isset($templates[$panel])) {
            throw new Not_Found_Http_Exception(\sprintf('Panel "%s" is not registered in profiler or is not present in viewed profile.', $panel));
        }
        return $templates[$panel];
    }
    /**
     * Gets template names of templates that are present in the viewed profile.
     *
     * @throws \UnexpectedValueException
     */
    public function get_names(Profile $profile): array
    {
        $loader = $this->twig->get_loader();
        $templates = [];
        foreach ($this->templates as $arguments) {
            if (null === $arguments) {
                continue;
            }
            [$name, $template] = $arguments;
            if (!$this->profiler->has($name)) {
                continue;
            }
            if (!$profile->has_collector($name)) {
                continue;
            }
            if (str_ends_with((string) $template, '.html.twig')) {
                $template = substr((string) $template, 0, -10);
            }
            if (!$loader->exists($template . '.html.twig')) {
                throw new \UnexpectedValueException(\sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
            }
            $templates[$name] = $template . '.html.twig';
        }
        return $templates;
    }
}