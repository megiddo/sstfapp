<?php

declare(strict_types=1);

namespace Sstf\Api\Tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Infrastructure\Google\GoogleIdTokenVerifierInterface;
use Sstf\Api\Tests\Fakes\FakeClock;
use Sstf\Api\Tests\Fakes\FakeGoogleIdTokenVerifier;

abstract class HttpTestCase extends TestCase
{
    protected App $app;

    protected FakeGoogleIdTokenVerifier $googleVerifier;

    protected FakeClock $clock;

    protected string $dataDir;

    /** @var array<string, string> */
    protected array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataDir = sys_get_temp_dir() . '/sstf-http-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->dataDir . '/users', 0700, true);

        $_ENV['DATA_PATH'] = $this->dataDir;
        $_ENV['SESSION_PATH'] = $this->dataDir . '/sessions';
        $_ENV['SESSION_SECRET'] = 'testing-session-secret-key';
        $_ENV['GOOGLE_CLIENT_ID'] = 'test-google-client-id.apps.googleusercontent.com';
        $_ENV['APP_ENV'] = 'testing';
        putenv('DATA_PATH=' . $this->dataDir);
        putenv('SESSION_PATH=' . $this->dataDir . '/sessions');
        putenv('SESSION_SECRET=testing-session-secret-key');
        putenv('GOOGLE_CLIENT_ID=' . $_ENV['GOOGLE_CLIENT_ID']);
        putenv('APP_ENV=testing');

        $this->cookies = [];
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $this->clock = new FakeClock(1_703_116_800);
        $this->app = require dirname(__DIR__) . '/config/bootstrap.php';

        $container = $this->app->getContainer();
        $this->assertInstanceOf(Container::class, $container);
        $container->set(GoogleIdTokenVerifierInterface::class, $this->googleVerifier);
        $container->set(ClockInterface::class, $this->clock);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->dataDir);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    protected function request(
        string $method,
        string $uri,
        ?array $json = null,
        array $headers = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        $request = $request->withCookieParams($this->cookies);

        if ($this->cookies !== []) {
            $parts = [];
            foreach ($this->cookies as $name => $value) {
                $parts[] = $name . '=' . $value;
            }
            $request = $request->withHeader('Cookie', implode('; ', $parts));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($json !== null) {
            $body = (new StreamFactory())->createStream(
                (string) json_encode($json, JSON_THROW_ON_ERROR),
            );
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body)
                ->withParsedBody($json);
        }

        $response = $this->app->handle($request);
        $this->captureCookies($response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);

        return $payload;
    }

    protected function freezeAt(string $datetime, string $timezone): void
    {
        $at = new \DateTimeImmutable($datetime, new \DateTimeZone($timezone));
        $this->clock->setTimestamp($at->getTimestamp());
    }

    protected function userDbPath(string $email): string
    {
        return $this->dataDir . '/users/' . md5(strtolower(trim($email))) . '.sqlite';
    }

    protected function signIn(string $email, ?string $timezone = null): void
    {
        $token = 'signin-' . $email . '-' . bin2hex(random_bytes(4));
        $this->googleVerifier->willVerify($token, FakeGoogleIdTokenVerifier::user($email));
        $body = ['id_token' => $token];
        if ($timezone !== null) {
            $body['timezone'] = $timezone;
        }

        $response = $this->request('POST', '/api/auth/google', $body);
        $this->assertSame(200, $response->getStatusCode());
    }

    private function captureCookies(ResponseInterface $response): void
    {
        foreach ($response->getHeader('Set-Cookie') as $line) {
            $pair = explode(';', $line, 2)[0];
            $eq = strpos($pair, '=');
            if ($eq === false) {
                continue;
            }

            $name = substr($pair, 0, $eq);
            $value = substr($pair, $eq + 1);
            $expired = $value === '' || preg_match('/Max-Age=0(?:;|$)/i', $line) === 1;
            if ($expired) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
