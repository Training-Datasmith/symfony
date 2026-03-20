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
namespace Symfony\Component\Http_Foundation;

/**
 * RedirectResponse represents an HTTP response doing a redirect.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Redirect_Response extends Response
{
    protected string $target_url;
    /**
     * Creates a redirect response so that it conforms to the rules defined for a redirect status code.
     *
     * @param string $url     The URL to redirect to. The URL should be a full URL, with schema etc.,
     *                        but practically every browser redirects on paths only as well
     * @param int    $status  The HTTP status code (302 "Found" by default)
     * @param array  $headers The headers (Location is always set to the given URL)
     *
     * @throws \InvalidArgumentException
     *
     * @see https://tools.ietf.org/html/rfc2616#section-10.3
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->set_target_url($url);
        if (!$this->is_redirect()) {
            throw new \InvalidArgumentException(\sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
        }
        if (301 == $status && !\array_key_exists('cache-control', array_change_key_case($headers, \CASE_LOWER))) {
            $this->headers->remove('cache-control');
        }
    }
    /**
     * Returns the target URL.
     */
    public function get_target_url(): string
    {
        return $this->target_url;
    }
    /**
     * Sets the redirect target of this response.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function set_target_url(string $url): static
    {
        if ('' === $url) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }
        $this->target_url = $url;
        $this->set_content(\sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, \ENT_QUOTES, 'UTF-8')));
        $this->headers->set('Location', $url);
        $this->headers->set('Content-Type', 'text/html; charset=utf-8');
        return $this;
    }
}