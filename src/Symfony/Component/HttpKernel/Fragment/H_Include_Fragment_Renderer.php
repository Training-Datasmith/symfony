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
namespace Symfony\Component\Http_Kernel\Fragment;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Uri_Signer;
use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
use Twig\Environment;
/**
 * Implements the Hinclude rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class H_Include_Fragment_Renderer extends Routable_Fragment_Renderer
{
    /**
     * @param string|null $globalDefaultTemplate The global default content (it can be a template name or the content)
     */
    public function __construct(private readonly ?Environment $twig = null, private readonly ?Uri_Signer $signer = null, private readonly ?string $global_default_template = null, private readonly string $charset = 'utf-8')
    {
    }
    /**
     * Checks if a templating engine has been set.
     */
    public function has_templating(): bool
    {
        return null !== $this->twig;
    }
    /**
     * Additional available options:
     *
     *  * default:    The default content (it can be a template name or the content)
     *  * id:         An optional hx:include tag id attribute
     *  * attributes: An optional array of hx:include tag attributes
     */
    public function render(string|Controller_Reference $uri, Request $request, array $options = []): Response
    {
        if ($uri instanceof Controller_Reference) {
            $uri = (new Fragment_Uri_Generator($this->fragment_path, $this->signer))->generate($uri, $request);
        }
        // We need to replace ampersands in the URI with the encoded form in order to return valid html/xml content.
        $uri = str_replace('&', '&amp;', $uri);
        $template = $options['default'] ?? $this->global_default_template;
        if (null !== $this->twig && $template && $this->twig->get_loader()->exists($template)) {
            $content = $this->twig->render($template);
        } else {
            $content = $template;
        }
        $attributes = isset($options['attributes']) && \is_array($options['attributes']) ? $options['attributes'] : [];
        if (isset($options['id']) && $options['id']) {
            $attributes['id'] = $options['id'];
        }
        $rendered_attributes = '';
        if (\count($attributes) > 0) {
            $flags = \ENT_QUOTES | \ENT_SUBSTITUTE;
            foreach ($attributes as $attribute => $value) {
                $rendered_attributes .= \sprintf(' %s="%s"', htmlspecialchars((string) $attribute, $flags, $this->charset, false), htmlspecialchars((string) $value, $flags, $this->charset, false));
            }
        }
        return new Response(\sprintf('<hx:include src="%s"%s>%s</hx:include>', $uri, $rendered_attributes, $content));
    }
    public function get_name(): string
    {
        return 'hinclude';
    }
}