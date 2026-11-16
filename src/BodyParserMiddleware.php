<?php

declare(strict_types=1);

namespace G\HttpMethodMiddleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Fills the parsed body of the requests PHP does not parse by itself:
 * URL-encoded bodies of PUT, PATCH and DELETE requests, and JSON bodies of any method.
 *
 * The body is read from the PSR-7 stream. A request that already has a parsed body,
 * or whose body is empty, invalid or of another content type, is passed on unchanged.
 */
final class BodyParserMiddleware implements MiddlewareInterface
{
    private const string TYPE_FORM = 'application/x-www-form-urlencoded';
    private const string TYPE_JSON = 'application/json';
    private const string SUFFIX_JSON = '+json';

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        if ($parsedBody === null || $parsedBody === []) {
            $data = $this->parse($request);

            if ($data !== null) {
                $request = $request->withParsedBody($data);
            }
        }

        return $handler->handle($request);
    }

    /**
     * @return array<mixed>|null Null when the body cannot be parsed
     */
    private function parse(ServerRequestInterface $request): ?array
    {
        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        $isJson = $contentType === self::TYPE_JSON || str_ends_with($contentType, self::SUFFIX_JSON);

        if (!$isJson && $contentType !== self::TYPE_FORM) {
            return null;
        }

        $content = $this->readBody($request);

        if ($content === '') {
            return null;
        }

        if ($isJson) {
            $data = json_decode($content, true);

            return is_array($data) ? $data : null;
        }

        parse_str($content, $data);

        return $data;
    }

    /**
     * Reads the whole body and leaves the stream where the next reader expects it.
     */
    private function readBody(ServerRequestInterface $request): string
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $content = $body->getContents();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $content;
    }
}
