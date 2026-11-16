<?php

declare(strict_types=1);

namespace G\HttpMethodMiddleware\Tests;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Home-made PSR-7 stubs: no concrete implementation is needed to test the middlewares.
 */
trait RequestTrait
{
    /**
     * @param array<string,string>    $headers
     * @param array<mixed>|object|null $parsedBody
     */
    private function request(
        string $method,
        array $headers = [],
        array|object|null $parsedBody = null,
        string $body = '',
        bool $seekable = true,
    ): ServerRequestInterface {
        $position = 0;
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn($seekable);
        $stream->method('rewind')->willReturnCallback(static function () use (&$position): void {
            $position = 0;
        });
        $stream->method('getContents')->willReturnCallback(static function () use (&$position, $body): string {
            $content = substr($body, $position);
            $position = strlen($body);

            return $content;
        });

        $headers = array_change_key_case($headers, CASE_LOWER);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getParsedBody')->willReturn($parsedBody);
        $request->method('getBody')->willReturn($stream);
        $request->method('getHeaderLine')->willReturnCallback(
            static fn(string $name): string => $headers[strtolower($name)] ?? ''
        );
        $request->method('withMethod')->willReturnCallback(
            fn(string $newMethod): ServerRequestInterface
                => $this->request($newMethod, $headers, $parsedBody, $body, $seekable)
        );
        $request->method('withParsedBody')->willReturnCallback(
            fn(array|object|null $data): ServerRequestInterface
                => $this->request($method, $headers, $data, $body, $seekable)
        );

        return $request;
    }

    /**
     * Runs the middleware and returns the request received by the next handler.
     */
    private function handledRequest(
        MiddlewareInterface $middleware,
        ServerRequestInterface $request
    ): ServerRequestInterface {
        $response = $this->createStub(ResponseInterface::class);

        $handler = new class ($response) implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return $this->response;
            }
        };

        $this->assertSame($response, $middleware->process($request, $handler));
        $this->assertNotNull($handler->request);

        return $handler->request;
    }
}
