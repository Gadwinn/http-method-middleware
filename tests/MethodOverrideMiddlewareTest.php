<?php

declare(strict_types=1);

namespace G\HttpMethodMiddleware\Tests;

use G\HttpMethodMiddleware\MethodOverrideMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MethodOverrideMiddlewareTest extends TestCase
{
    use RequestTrait;

    #[DataProvider('allowedMethodsProvider')]
    public function testPostIsOverriddenByTheBodyField(string $value, string $expected): void
    {
        $request = $this->request('POST', parsedBody: ['_method' => $value]);

        $this->assertSame($expected, $this->handledRequest(new MethodOverrideMiddleware(), $request)->getMethod());
    }

    /** @return array<string, array{string, string}> */
    public static function allowedMethodsProvider(): array
    {
        return [
            'PUT' => ['PUT', 'PUT'],
            'PATCH' => ['PATCH', 'PATCH'],
            'DELETE' => ['DELETE', 'DELETE'],
            'lower case' => ['delete', 'DELETE'],
            'spaces' => [' put ', 'PUT'],
        ];
    }

    public function testPostIsOverriddenByTheHeader(): void
    {
        $request = $this->request('POST', ['X-HTTP-Method-Override' => 'PATCH']);

        $this->assertSame('PATCH', $this->handledRequest(new MethodOverrideMiddleware(), $request)->getMethod());
    }

    public function testBodyFieldWinsOverTheHeader(): void
    {
        $request = $this->request('POST', ['X-HTTP-Method-Override' => 'PATCH'], ['_method' => 'DELETE']);

        $this->assertSame('DELETE', $this->handledRequest(new MethodOverrideMiddleware(), $request)->getMethod());
    }

    public function testLowerCasePostIsOverridden(): void
    {
        $request = $this->request('post', parsedBody: ['_method' => 'PUT']);

        $this->assertSame('PUT', $this->handledRequest(new MethodOverrideMiddleware(), $request)->getMethod());
    }

    #[DataProvider('notOverridableProvider')]
    public function testOnlyPostCanBeOverridden(string $method): void
    {
        $request = $this->request($method, ['X-HTTP-Method-Override' => 'DELETE'], ['_method' => 'DELETE']);

        $this->assertSame($request, $this->handledRequest(new MethodOverrideMiddleware(), $request));
    }

    /** @return array<string, array{string}> */
    public static function notOverridableProvider(): array
    {
        return ['GET' => ['GET'], 'HEAD' => ['HEAD'], 'PUT' => ['PUT'], 'OPTIONS' => ['OPTIONS']];
    }

    #[DataProvider('refusedValuesProvider')]
    public function testMethodOutsideTheAllowedListIsIgnored(mixed $value): void
    {
        $request = $this->request('POST', parsedBody: ['_method' => $value]);

        $this->assertSame($request, $this->handledRequest(new MethodOverrideMiddleware(), $request));
    }

    /** @return array<string, array{mixed}> */
    public static function refusedValuesProvider(): array
    {
        return [
            'GET' => ['GET'],
            'TRACE' => ['TRACE'],
            'CONNECT' => ['CONNECT'],
            'unknown' => ['HACK'],
            'empty' => [''],
            'array' => [['PUT']],
            'integer' => [1],
        ];
    }

    public function testRequestWithoutOverrideIsUntouched(): void
    {
        $request = $this->request('POST', parsedBody: ['name' => 'value']);

        $this->assertSame($request, $this->handledRequest(new MethodOverrideMiddleware(), $request));
    }

    public function testObjectBodyIsIgnored(): void
    {
        $request = $this->request('POST', parsedBody: (object) ['_method' => 'PUT']);

        $this->assertSame($request, $this->handledRequest(new MethodOverrideMiddleware(), $request));
    }

    public function testCustomAllowedMethodsFieldAndHeader(): void
    {
        $middleware = new MethodOverrideMiddleware(['delete'], '__m', 'X-Method');

        $byField = $this->request('POST', parsedBody: ['__m' => 'DELETE']);
        $byHeader = $this->request('POST', ['X-Method' => 'DELETE']);
        $refused = $this->request('POST', parsedBody: ['__m' => 'PUT']);
        $defaultField = $this->request('POST', ['X-HTTP-Method-Override' => 'DELETE'], ['_method' => 'DELETE']);

        $this->assertSame('DELETE', $this->handledRequest($middleware, $byField)->getMethod());
        $this->assertSame('DELETE', $this->handledRequest($middleware, $byHeader)->getMethod());
        $this->assertSame($refused, $this->handledRequest($middleware, $refused));
        $this->assertSame($defaultField, $this->handledRequest($middleware, $defaultField));
    }

    public function testFieldAndHeaderCanBeDisabled(): void
    {
        $noField = new MethodOverrideMiddleware(field: '');
        $noHeader = new MethodOverrideMiddleware(header: '');

        $byField = $this->request('POST', parsedBody: ['_method' => 'PUT', '' => 'PUT']);
        $byHeader = $this->request('POST', ['X-HTTP-Method-Override' => 'PUT', '' => 'PUT']);

        $this->assertSame($byField, $this->handledRequest($noField, $byField));
        $this->assertSame('PUT', $this->handledRequest($noField, $byHeader)->getMethod());
        $this->assertSame($byHeader, $this->handledRequest($noHeader, $byHeader));
        $this->assertSame('PUT', $this->handledRequest($noHeader, $byField)->getMethod());
    }
}
