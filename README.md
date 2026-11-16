# gadwinn/http-method-middleware

Two independent PSR-15 middlewares around HTTP methods:

- `MethodOverrideMiddleware` lets an HTML form (limited to GET and POST) send a `PUT`, `PATCH` or `DELETE` request.
- `BodyParserMiddleware` fills the parsed body of the requests PHP does not parse by itself (`PUT`/`PATCH`/`DELETE` forms, JSON).

Use one, the other, or both. No dependency besides the PSR interfaces, no global state, nothing read from `php://input` or the superglobals.

## Requirements

- PHP >= 8.3

## Installation

```bash
composer require gadwinn/http-method-middleware
```

## MethodOverrideMiddleware

```php
use G\HttpMethodMiddleware\MethodOverrideMiddleware;

$pipeline->pipe(new MethodOverrideMiddleware());
```

```html
<form method="post" action="/articles/42">
    <input type="hidden" name="_method" value="DELETE">
    <button>Delete</button>
</form>
```

The request reaches the next middleware (your router) as a `DELETE`.

The method is read from the `_method` field of the parsed body, then from the `X-HTTP-Method-Override` header. For safety:

- only `POST` requests can be overridden (never a `GET`, which a simple link could trigger);
- only `PUT`, `PATCH` and `DELETE` are accepted; any other value is ignored and the request stays a `POST`.

Everything is configurable:

```php
new MethodOverrideMiddleware(
    allowedMethods: ['PUT', 'PATCH', 'DELETE'],
    field: '_method',                    // '' to ignore the body
    header: 'X-HTTP-Method-Override',    // '' to ignore the header
);
```

Place it **before** the router, and before a CSRF middleware that only checks some methods.

## BodyParserMiddleware

```php
use G\HttpMethodMiddleware\BodyParserMiddleware;

$pipeline->pipe(new BodyParserMiddleware());

// In a handler:
$data = $request->getParsedBody(); // ['title' => 'Hello']
```

PHP only fills `$_POST` for `POST` requests sent as a form. This middleware parses the body, read from the PSR-7 stream, for:

| Content-Type | Methods | Result |
|---|---|---|
| `application/x-www-form-urlencoded` | any (in practice `PUT`, `PATCH`, `DELETE`) | same array as `$_POST` would give |
| `application/json`, `application/*+json` | any | decoded array (objects become associative arrays) |

The request is passed on unchanged when it already has a non-empty parsed body, or when its body is empty, of another content type, or not valid (malformed JSON, JSON scalar): check `getParsedBody()` in your handler and answer `400` if the data you expect is missing.

`multipart/form-data` (file uploads) is not parsed: send uploads with `POST`, which PHP handles natively.

## Using both

```php
$pipeline->pipe(new MethodOverrideMiddleware());
$pipeline->pipe(new BodyParserMiddleware());
$pipeline->pipe($router);
```

## Development

```bash
composer install
composer test-all
```

## License

MIT
