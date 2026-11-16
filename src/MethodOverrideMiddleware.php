<?php

declare(strict_types=1);

namespace G\HttpMethodMiddleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Lets a POST request declare another HTTP method, for clients that can only send GET and POST
 * (HTML forms): the method is read from a body field, then from a header.
 *
 * Only POST requests can be overridden, and only with one of the allowed methods:
 * anything else is ignored and the request is passed on unchanged.
 */
final class MethodOverrideMiddleware implements MiddlewareInterface
{
    public const string DEFAULT_FIELD = '_method';
    public const string DEFAULT_HEADER = 'X-HTTP-Method-Override';

    private const string METHOD_POST = 'POST';

    /** @var list<string> */
    private readonly array $allowedMethods;

    /**
     * @param string[] $allowedMethods Methods a POST request may turn into
     * @param string   $field          Name of the parsed body field ('' to disable)
     * @param string   $header         Name of the header ('' to disable)
     */
    public function __construct(
        array $allowedMethods = ['PUT', 'PATCH', 'DELETE'],
        private readonly string $field = self::DEFAULT_FIELD,
        private readonly string $header = self::DEFAULT_HEADER,
    ) {
        $this->allowedMethods = array_values(array_map(strtoupper(...), $allowedMethods));
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === self::METHOD_POST) {
            $method = strtoupper(trim($this->readOverride($request)));

            if (in_array($method, $this->allowedMethods, true)) {
                $request = $request->withMethod($method);
            }
        }

        return $handler->handle($request);
    }

    private function readOverride(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();

        if ($this->field !== '' && is_array($body)) {
            $value = $body[$this->field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->header !== '' ? $request->getHeaderLine($this->header) : '';
    }
}
