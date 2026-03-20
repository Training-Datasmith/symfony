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
namespace Symfony\Bundle\Web_Profiler_Bundle\Csp;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 * Handles Content-Security-Policy HTTP header for the WebProfiler Bundle.
 *
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @internal
 */
class Content_Security_Policy_Handler
{
    private bool $csp_disabled = false;
    public function __construct(private readonly Nonce_Generator $nonce_generator)
    {
    }
    /**
     * Returns an array of nonces to be used in Twig templates and Content-Security-Policy headers.
     *
     * Nonce can be provided by;
     *  - The request - In case HTML content is fetched via AJAX and inserted in DOM, it must use the same nonce as origin
     *  - The response -  A call to getNonces() has already been done previously. Same nonce are returned
     *  - They are otherwise randomly generated
     */
    public function get_nonces(Request $request, Response $response): array
    {
        if ($request->headers->has('X-SymfonyProfiler-Script-Nonce') && $request->headers->has('X-SymfonyProfiler-Style-Nonce')) {
            return ['csp_script_nonce' => $request->headers->get('X-SymfonyProfiler-Script-Nonce'), 'csp_style_nonce' => $request->headers->get('X-SymfonyProfiler-Style-Nonce')];
        }
        if ($response->headers->has('X-SymfonyProfiler-Script-Nonce') && $response->headers->has('X-SymfonyProfiler-Style-Nonce')) {
            return ['csp_script_nonce' => $response->headers->get('X-SymfonyProfiler-Script-Nonce'), 'csp_style_nonce' => $response->headers->get('X-SymfonyProfiler-Style-Nonce')];
        }
        $nonces = ['csp_script_nonce' => $this->generate_nonce(), 'csp_style_nonce' => $this->generate_nonce()];
        $response->headers->set('X-SymfonyProfiler-Script-Nonce', $nonces['csp_script_nonce']);
        $response->headers->set('X-SymfonyProfiler-Style-Nonce', $nonces['csp_style_nonce']);
        return $nonces;
    }
    /**
     * Disables Content-Security-Policy.
     *
     * All related headers will be removed.
     */
    public function disable_csp(): void
    {
        $this->csp_disabled = true;
    }
    /**
     * Cleanup temporary headers and updates Content-Security-Policy headers.
     *
     * @return array Nonces used by the bundle in Content-Security-Policy header
     */
    public function update_response_headers(Request $request, Response $response): array
    {
        if ($this->csp_disabled) {
            $this->remove_csp_headers($response);
            return [];
        }
        $nonces = $this->get_nonces($request, $response);
        $this->clean_headers($response);
        $this->update_csp_headers($response, $nonces);
        return $nonces;
    }
    private function clean_headers(Response $response): void
    {
        $response->headers->remove('X-SymfonyProfiler-Script-Nonce');
        $response->headers->remove('X-SymfonyProfiler-Style-Nonce');
    }
    private function remove_csp_headers(Response $response): void
    {
        $response->headers->remove('X-Content-Security-Policy');
        $response->headers->remove('Content-Security-Policy');
        $response->headers->remove('Content-Security-Policy-Report-Only');
    }
    /**
     * Updates Content-Security-Policy headers in a response.
     */
    private function update_csp_headers(Response $response, array $nonces = []): array
    {
        $nonces = array_replace(['csp_script_nonce' => $this->generate_nonce(), 'csp_style_nonce' => $this->generate_nonce()], $nonces);
        $rule_is_set = false;
        $headers = $this->get_csp_headers($response);
        $types = ['script-src' => 'csp_script_nonce', 'script-src-elem' => 'csp_script_nonce', 'style-src' => 'csp_style_nonce', 'style-src-elem' => 'csp_style_nonce'];
        foreach ($headers as $header => $directives) {
            foreach ($types as $type => $token_name) {
                if ($this->authorizes_inline($directives, $type)) {
                    continue;
                }
                if (!isset($headers[$header][$type])) {
                    if (null === $fallback = $this->get_directive_fallback($directives, $type)) {
                        continue;
                    }
                    if (['\'none\''] === $fallback) {
                        // Fallback came from "default-src: 'none'"
                        // 'none' is invalid if it's not the only expression in the source list, so we leave it out
                        $fallback = [];
                    }
                    $headers[$header][$type] = $fallback;
                }
                $rule_is_set = true;
                if (!\in_array('\'unsafe-inline\'', $headers[$header][$type], true)) {
                    $headers[$header][$type][] = '\'unsafe-inline\'';
                }
                $headers[$header][$type][] = \sprintf('\'nonce-%s\'', $nonces[$token_name]);
            }
        }
        if (!$rule_is_set) {
            return $nonces;
        }
        foreach ($headers as $header => $directives) {
            $response->headers->set($header, $this->generate_csp_header($directives));
        }
        return $nonces;
    }
    /**
     * Generates a valid Content-Security-Policy nonce.
     */
    private function generate_nonce(): string
    {
        return $this->nonce_generator->generate();
    }
    /**
     * Converts a directive set array into Content-Security-Policy header.
     */
    private function generate_csp_header(array $directives): string
    {
        return array_reduce(array_keys($directives), static fn($res, string $name): string => ('' !== $res ? $res . '; ' : '') . \sprintf('%s %s', $name, implode(' ', $directives[$name])), '');
    }
    /**
     * Converts a Content-Security-Policy header value into a directive set array.
     */
    private function parse_directives(string $header): array
    {
        $directives = [];
        foreach (explode(';', $header) as $directive) {
            $parts = explode(' ', trim($directive));
            if (\count($parts) < 1) {
                continue;
            }
            $name = array_shift($parts);
            $directives[$name] = $parts;
        }
        return $directives;
    }
    /**
     * Detects if the 'unsafe-inline' is prevented for a directive within the directive set.
     */
    private function authorizes_inline(array $directives_set, string $type): bool
    {
        if (isset($directives_set[$type])) {
            $directives = $directives_set[$type];
        } elseif (null === $directives = $this->get_directive_fallback($directives_set, $type)) {
            return false;
        }
        return \in_array('\'unsafe-inline\'', $directives, true) && !$this->has_hash_or_nonce($directives);
    }
    private function has_hash_or_nonce(array $directives): bool
    {
        foreach ($directives as $directive) {
            if (!str_ends_with((string) $directive, '\'')) {
                continue;
            }
            if (str_starts_with((string) $directive, '\'nonce-')) {
                return true;
            }
            if (\in_array(substr((string) $directive, 0, 8), ['\'sha256-', '\'sha384-', '\'sha512-'], true)) {
                return true;
            }
        }
        return false;
    }
    private function get_directive_fallback(array $directive_set, string $type): ?array
    {
        if (\in_array($type, ['script-src-elem', 'style-src-elem'], true) || !isset($directive_set['default-src'])) {
            // Let the browser fallback on it's own
            return null;
        }
        return $directive_set['default-src'];
    }
    /**
     * Retrieves the Content-Security-Policy headers (either X-Content-Security-Policy or Content-Security-Policy) from
     * a response.
     */
    private function get_csp_headers(Response $response): array
    {
        $headers = [];
        if ($response->headers->has('Content-Security-Policy')) {
            $headers['Content-Security-Policy'] = $this->parse_directives($response->headers->get('Content-Security-Policy'));
        }
        if ($response->headers->has('Content-Security-Policy-Report-Only')) {
            $headers['Content-Security-Policy-Report-Only'] = $this->parse_directives($response->headers->get('Content-Security-Policy-Report-Only'));
        }
        if ($response->headers->has('X-Content-Security-Policy')) {
            $headers['X-Content-Security-Policy'] = $this->parse_directives($response->headers->get('X-Content-Security-Policy'));
        }
        return $headers;
    }
}