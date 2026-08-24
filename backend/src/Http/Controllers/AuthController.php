<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\AccountExistsException;
use Sstf\Api\Domain\EmailUnverifiedException;
use Sstf\Api\Domain\InvalidCredentialsException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\InvalidPasswordException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\RedirectResponder;
use Sstf\Api\Infrastructure\Google\GoogleOAuthClientInterface;
use Sstf\Api\Infrastructure\Google\OAuthStateService;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Services\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
        private readonly GoogleOAuthClientInterface $googleOAuth,
        private readonly OAuthStateService $oauthState,
        private readonly string $appUrl = '',
    ) {
    }

    public function googleStart(Request $request, Response $response): Response
    {
        if (!$this->googleOAuth->isConfigured()) {
            return $this->oauthFailureRedirect('google');
        }

        $query = $request->getQueryParams();
        $timezone = $query['timezone'] ?? null;
        if ($timezone !== null && !is_string($timezone)) {
            $timezone = null;
        }

        $issued = $this->oauthState->issue($timezone);
        try {
            $url = $this->googleOAuth->authorizationUrl($issued['state']);
        } catch (InvalidGoogleIdTokenException) {
            return $this->oauthFailureRedirect('google');
        }

        return RedirectResponder::to($url)
            ->withAddedHeader('Set-Cookie', $issued['header']);
    }

    public function googleCallback(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $stored = $this->oauthState->read($request);
        $expireOauth = $this->oauthState->expireHeader();

        $googleError = $query['error'] ?? null;
        if (is_string($googleError) && $googleError !== '') {
            return $this->oauthFailureRedirect('google', $expireOauth);
        }

        $state = $query['state'] ?? null;
        $code = $query['code'] ?? null;
        if (
            $stored === null
            || !is_string($state)
            || $state === ''
            || !is_string($code)
            || trim($code) === ''
            || !hash_equals($stored['state'], $state)
        ) {
            return $this->oauthFailureRedirect('google', $expireOauth);
        }

        try {
            $user = $this->googleOAuth->fetchUser($code);
            $result = $this->auth->signInWithGoogle($user, $stored['timezone']);
        } catch (EmailUnverifiedException) {
            return $this->oauthFailureRedirect('email_unverified', $expireOauth);
        } catch (InvalidGoogleIdTokenException) {
            return $this->oauthFailureRedirect('google', $expireOauth);
        }

        return RedirectResponder::to($this->spaLocation('/'))
            ->withAddedHeader('Set-Cookie', $this->sessions->setCookieHeader($result['cookie']))
            ->withAddedHeader('Set-Cookie', $expireOauth);
    }

    public function password(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Sign-in failed', 400);
        }

        $email = $body['username'] ?? $body['email'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($email) || trim($email) === '' || !is_string($password) || $password === '') {
            return JsonResponder::error('invalid_request', 'Sign-in failed', 400);
        }

        try {
            $result = $this->auth->signInWithPassword($email, $password);
        } catch (InvalidCredentialsException) {
            return JsonResponder::error('invalid_credentials', 'Sign-in failed', 401);
        }

        return JsonResponder::data($result['account']->toApi())
            ->withAddedHeader('Set-Cookie', $this->sessions->setCookieHeader($result['cookie']));
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Registration failed', 400);
        }

        $email = $body['username'] ?? $body['email'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($email) || trim($email) === '' || !is_string($password)) {
            return JsonResponder::error('invalid_request', 'Registration failed', 400);
        }

        $timezone = $body['timezone'] ?? null;
        if ($timezone !== null && !is_string($timezone)) {
            $timezone = null;
        }

        try {
            $result = $this->auth->registerWithPassword($email, $password, $timezone);
        } catch (InvalidPasswordException) {
            return JsonResponder::error('invalid_password', 'Enter a password', 400);
        } catch (AccountExistsException) {
            return JsonResponder::error('account_exists', 'Account already exists', 409);
        } catch (InvalidCredentialsException) {
            return JsonResponder::error('invalid_request', 'Registration failed', 400);
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

    private function oauthFailureRedirect(string $error, ?string $expireOauth = null): Response
    {
        $response = RedirectResponder::to($this->spaLocation('/login?error=' . rawurlencode($error)));
        if ($expireOauth !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $expireOauth);
        }

        return $response;
    }

    private function spaLocation(string $path): string
    {
        if ($this->appUrl === '') {
            return $path;
        }

        return rtrim($this->appUrl, '/') . $path;
    }
}
