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
namespace Symfony\Bundle\Framework_Bundle\Controller;

use Symfony\Component\Http_Foundation\Response;
use Twig\Environment;
/**
 * TemplateController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Template_Controller
{
    public function __construct(private readonly ?Environment $twig = null)
    {
    }
    /**
     * Renders a template.
     *
     * @param string    $template   The template name
     * @param int|null  $maxAge     Max age for client caching
     * @param int|null  $sharedAge  Max age for shared (proxy) caching
     * @param bool|null $private    Whether or not caching should apply for client caches only
     * @param array     $context    The context (arguments) of the template
     * @param int       $statusCode The HTTP status code to return with the response (200 "OK" by default)
     * @param array     $headers    The HTTP headers to add to the response
     */
    public function template_action(string $template, ?int $max_age = null, ?int $shared_age = null, ?bool $private = null, array $context = [], int $status_code = 200, array $headers = []): Response
    {
        if (null === $this->twig) {
            throw new \LogicException('You cannot use the TemplateController if the Twig Bundle is not available. Try running "composer require symfony/twig-bundle".');
        }
        $response = new Response($this->twig->render($template, $context), $status_code);
        if ($max_age) {
            $response->set_max_age($max_age);
        }
        if (null !== $shared_age) {
            $response->set_shared_max_age($shared_age);
        }
        if ($private) {
            $response->set_private();
        } elseif (false === $private || null === $private && (null !== $max_age || null !== $shared_age)) {
            $response->set_public();
        }
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }
        return $response;
    }
    /**
     * @param int $statusCode The HTTP status code (200 "OK" by default)
     */
    public function __invoke(string $template, ?int $max_age = null, ?int $shared_age = null, ?bool $private = null, array $context = [], int $status_code = 200, array $headers = []): Response
    {
        return $this->template_action($template, $max_age, $shared_age, $private, $context, $status_code, $headers);
    }
}