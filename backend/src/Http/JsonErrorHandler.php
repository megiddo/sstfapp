<?php

declare(strict_types=1);

namespace Sstf\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

final class JsonErrorHandler
{
    public function __construct(
        private readonly bool $displayErrorDetails,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        return $this->respond($exception, $displayErrorDetails);
    }

    public function handle(Throwable $exception): ResponseInterface
    {
        return $this->respond($exception, $this->displayErrorDetails);
    }

    private function respond(Throwable $exception, bool $displayErrorDetails): ResponseInterface
    {
        if ($exception instanceof HttpException) {
            $status = $exception->getCode();
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            $message = $exception->getMessage();
            if ($message === '') {
                $message = 'HTTP error';
            }

            return JsonResponder::error('http_error', $message, $status);
        }

        $message = $displayErrorDetails
            ? $exception->getMessage()
            : 'Internal server error';

        if ($message === '') {
            $message = 'Internal server error';
        }

        return JsonResponder::error('internal_error', $message, 500);
    }
}
