<?php

declare(strict_types=1);

/**
 * Example 1: HttpFoundation — Request, Response, and Cookie
 *
 * Demonstrates creating and manipulating HTTP request/response objects.
 * This example is runnable standalone via the CLI or a web server.
 *
 * Usage (CLI simulation):
 *   php examples/01_http_foundation.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// ---------------------------------------------------------------------------
// 1. Creating a request programmatically (useful in tests and sub-requests)
// ---------------------------------------------------------------------------

$request = Request::create(
    uri: '/api/users/42',
    method: 'GET',
    server: ['HTTP_ACCEPT' => 'application/json'],
);

echo 'Method: ' . $request->getMethod() . PHP_EOL;          // GET
echo 'Path:   ' . $request->getPathInfo() . PHP_EOL;        // /api/users/42
echo 'Format: ' . $request->getRequestFormat() . PHP_EOL;   // html (default)

// ---------------------------------------------------------------------------
// 2. Building a JSON response
// ---------------------------------------------------------------------------

$data = ['id' => 42, 'name' => 'Jane Doe'];

$response = new Response(
    content: json_encode($data, JSON_THROW_ON_ERROR),
    status: Response::HTTP_OK,
    headers: ['Content-Type' => 'application/json'],
);

// Prepare adjusts Content-Type, protocol version, etc.
$response->prepare($request);

echo 'Status: ' . $response->getStatusCode() . PHP_EOL;     // 200
echo 'Body:   ' . $response->getContent() . PHP_EOL;        // {"id":42,"name":"Jane Doe"}

// ---------------------------------------------------------------------------
// 3. Attaching a cookie to the response
// ---------------------------------------------------------------------------

$cookie = Cookie::create(
    name: 'session_token',
    value: bin2hex(random_bytes(16)),
    expire: new DateTimeImmutable('+1 hour'),
    path: '/',
    secure: true,
    httpOnly: true,
    sameSite: Cookie::SAMESITE_STRICT,
);

$response->headers->setCookie($cookie);

echo 'Cookie set: ' . $cookie->getName() . PHP_EOL;         // session_token

// ---------------------------------------------------------------------------
// 4. Trusted proxy configuration (important for load-balanced deployments)
// ---------------------------------------------------------------------------

Request::setTrustedProxies(
    proxies: ['10.0.0.1', '10.0.0.2'],
    trustedHeaderSet: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
);

$proxiedRequest = Request::create('https://example.com/page');
$proxiedRequest->server->set('REMOTE_ADDR', '10.0.0.1');
$proxiedRequest->headers->set('X-Forwarded-For', '203.0.113.5');

echo 'Client IP: ' . $proxiedRequest->getClientIp() . PHP_EOL; // 203.0.113.5

// Reset for subsequent requests
Request::setTrustedProxies([], -1);
