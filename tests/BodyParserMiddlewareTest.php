<?php

declare(strict_types=1);

namespace G\HttpMethodMiddleware\Tests;

use G\HttpMethodMiddleware\BodyParserMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BodyParserMiddlewareTest extends TestCase
{
    use RequestTrait;

    private const string FORM = 'application/x-www-form-urlencoded';

    #[DataProvider('methodsProvider')]
    public function testUrlEncodedBodyIsParsed(string $method): void
    {
        $request = $this->request($method, ['Content-Type' => self::FORM], body: 'name=John+Doe&tags%5B%5D=a&tags%5B%5D=b');

        $this->assertSame(
            ['name' => 'John Doe', 'tags' => ['a', 'b']],
            $this->handledRequest(new BodyParserMiddleware(), $request)->getParsedBody()
        );
    }

    /** @return array<string, array{string}> */
    public static function methodsProvider(): array
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH'], 'DELETE' => ['DELETE'], 'POST' => ['POST']];
    }

    #[DataProvider('jsonTypesProvider')]
    public function testJsonBodyIsParsed(string $contentType): void
    {
        $request = $this->request('POST', ['Content-Type' => $contentType], body: '{"name":"John","roles":["admin"]}');

        $this->assertSame(
            ['name' => 'John', 'roles' => ['admin']],
            $this->handledRequest(new BodyParserMiddleware(), $request)->getParsedBody()
        );
    }

    /** @return array<string, array{string}> */
    public static function jsonTypesProvider(): array
    {
        return [
            'plain' => ['application/json'],
            'with charset' => ['application/json; charset=utf-8'],
            'upper case' => ['Application/JSON'],
            'suffix' => ['application/merge-patch+json'],
        ];
    }

    public function testContentTypeParametersAreIgnored(): void
    {
        $request = $this->request('PUT', ['Content-Type' => self::FORM . ' ; charset=UTF-8'], body: 'a=1');

        $this->assertSame(['a' => '1'], $this->handledRequest(new BodyParserMiddleware(), $request)->getParsedBody());
    }

    public function testAlreadyParsedBodyIsKept(): void
    {
        $request = $this->request('POST', ['Content-Type' => self::FORM], ['a' => '1'], 'b=2');

        $this->assertSame($request, $this->handledRequest(new BodyParserMiddleware(), $request));
    }

    public function testEmptyParsedBodyIsParsedAgain(): void
    {
        $request = $this->request('PUT', ['Content-Type' => self::FORM], [], 'b=2');

        $this->assertSame(['b' => '2'], $this->handledRequest(new BodyParserMiddleware(), $request)->getParsedBody());
    }

    #[DataProvider('untouchedProvider')]
    public function testRequestIsPassedOnUnchanged(string $contentType, string $body): void
    {
        $request = $this->request('PUT', ['Content-Type' => $contentType], body: $body);

        $this->assertSame($request, $this->handledRequest(new BodyParserMiddleware(), $request));
    }

    /** @return array<string, array{string, string}> */
    public static function untouchedProvider(): array
    {
        return [
            'no content type' => ['', 'a=1'],
            'other content type' => ['text/plain', 'a=1'],
            'multipart' => ['multipart/form-data; boundary=x', '--x--'],
            'empty form body' => [self::FORM, ''],
            'empty json body' => ['application/json', ''],
            'invalid json' => ['application/json', '{"a":'],
            'json scalar' => ['application/json', '42'],
            'json null' => ['application/json', 'null'],
        ];
    }

    public function testBodyStreamIsRewoundBeforeAndAfterReading(): void
    {
        $request = $this->request('PUT', ['Content-Type' => self::FORM], body: 'a=1');
        $request->getBody()->getContents(); // an earlier reader left the stream at its end

        $handled = $this->handledRequest(new BodyParserMiddleware(), $request);

        $this->assertSame(['a' => '1'], $handled->getParsedBody());
        $this->assertSame('a=1', $request->getBody()->getContents());
    }

    public function testNonSeekableBodyIsReadFromItsCurrentPosition(): void
    {
        $request = $this->request('PUT', ['Content-Type' => 'application/json'], body: '{"a":1}', seekable: false);

        $this->assertSame(['a' => 1], $this->handledRequest(new BodyParserMiddleware(), $request)->getParsedBody());
    }
}
