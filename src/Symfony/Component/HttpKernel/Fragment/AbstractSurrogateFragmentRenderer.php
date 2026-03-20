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
use Symfony\Component\Http_Kernel\Http_Cache\Surrogate_Interface;
/**
 * Implements Surrogate rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Abstract_Surrogate_Fragment_Renderer extends Routable_Fragment_Renderer
{
    /**
     * The "fallback" strategy when surrogate is not available should always be an
     * instance of InlineFragmentRenderer.
     *
     * @param FragmentRendererInterface $inlineStrategy The inline strategy to use when the surrogate is not supported
     */
    public function __construct(private readonly ?Surrogate_Interface $surrogate, private readonly Fragment_Renderer_Interface $inline_strategy, private readonly ?Uri_Signer $signer = null)
    {
    }
    /**
     * Note that if the current Request has no surrogate capability, this method
     * falls back to use the inline rendering strategy.
     *
     * Additional available options:
     *
     *  * alt: an alternative URI to render in case of an error
     *  * comment: a comment to add when returning the surrogate tag
     *  * absolute_uri: whether to generate an absolute URI or not. Default is false
     *
     * Note, that not all surrogate strategies support all options. For now
     * 'alt' and 'comment' are only supported by ESI.
     *
     * @see Symfony\Component\HttpKernel\HttpCache\SurrogateInterface
     */
    public function render(string|Controller_Reference $uri, Request $request, array $options = []): Response
    {
        if (!$this->surrogate || !$this->surrogate->has_surrogate_capability($request)) {
            $request->attributes->set('_check_controller_is_allowed', true);
            if ($uri instanceof Controller_Reference && $this->contains_non_scalars($uri->attributes)) {
                throw new \InvalidArgumentException('Passing non-scalar values as part of URI attributes to the ESI and SSI rendering strategies is not supported. Use a different rendering strategy or pass scalar values.');
            }
            return $this->inline_strategy->render($uri, $request, $options);
        }
        $absolute = $options['absolute_uri'] ?? false;
        if ($uri instanceof Controller_Reference) {
            $uri = $this->generate_signed_fragment_uri($uri, $request, $absolute);
        }
        $alt = $options['alt'] ?? null;
        if ($alt instanceof Controller_Reference) {
            $alt = $this->generate_signed_fragment_uri($alt, $request, $absolute);
        }
        $tag = $this->surrogate->render_include_tag($uri, $alt, $options['ignore_errors'] ?? false, $options['comment'] ?? '');
        return new Response($tag);
    }
    private function generate_signed_fragment_uri(Controller_Reference $uri, Request $request, bool $absolute): string
    {
        return (new Fragment_Uri_Generator($this->fragment_path, $this->signer))->generate($uri, $request, $absolute);
    }
    private function contains_non_scalars(array $values): bool
    {
        foreach ($values as $value) {
            if (\is_scalar($value)) {
                continue;
            }
            if (null === $value) {
                continue;
            }
            if (!\is_array($value) || $this->contains_non_scalars($value)) {
                return true;
            }
        }
        return false;
    }
}