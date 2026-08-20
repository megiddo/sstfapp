<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\EmailUnverifiedException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Services\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
    ) {
    }

    public function google(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Google sign-in failed', 400);
        }

        $token = $body['id_token'] ?? null;
        if (!is_string($token) || trim($token) === '') {
            return JsonResponder::error('invalid_request', 'Google sign-in failed', 400);
        }

        $timezone = $body['timezone'] ?? null;
        if ($timezone !== null && !is_string($timezone)) {
            $timezone = null;
        }

        try {
            $result = $this->auth->signInWithGoogle($token, $timezone);
        } catch (EmailUnverifiedException) {
            return JsonResponder::error('email_unverified', 'Email not verified', 401);
        } catch (InvalidGoogleIdTokenException) {
            return JsonResponder::error('invalid_token', 'Google sign-in failed', 401);
        }

        return JsonResponder::data($result['account']->toApi())
            ->withAddedHeader('Set-Cookie', $this->sessions->setCookieHeader($result['cookie']));
    }

    public function logout(Request $request, Response $response): Response
    {
        $cookie = $this->sessions->cookieValueFrom($request);
        $this->auth->logout($cookie);

        return JsonResponder::data(['ok' => true])
            ->withAddedHeader('Set-Cookie', $this->sessions->expireCookieHeader());
    }
}
