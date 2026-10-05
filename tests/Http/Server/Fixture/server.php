<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Http\Body\StreamBody;
use ExtendsSoftware\ExaPHP\Http\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Exception\ResponseEmissionException;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\Server\PhpResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\PhpServerRequestFactory;
use ExtendsSoftware\ExaPHP\Http\StatusCode;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$request = new PhpServerRequestFactory()->create();
$path = $request->uri->path();
$stream = fopen('php://memory', 'w+b');
fwrite($stream, 'streamed response');
rewind($stream);
$body = new StreamBody($stream);
$status = match ($path) {
    '/204' => StatusCode::NoContent,
    '/205' => StatusCode::ResetContent,
    '/304' => StatusCode::NotModified,
    default => StatusCode::Ok,
};
$headers = new Headers(['Set-Cookie' => ['a=1', 'b=2'], 'Content-Type' => 'text/plain']);
if ($path === '/echo') {
    $body = new StringBody($request->target() . '|' . $request->headers->get('X-Test')[0] . '|'
        . implode('', iterator_to_array($request->body->chunks())));
}
if ($path === '/location') {
    $headers = $headers->with('Location', '/destination');
}
if ($path === '/head' || in_array($path, ['/204', '/205', '/304'], true)) {
    // A forbidden body read must fail, even if the SAPI would discard its output.
    fclose($stream);
    $headers = $headers->with('Content-Length', '17');
}
if ($path === '/sent') {
    echo 'already sent';
    flush();
}
try {
    new PhpResponseEmitter()->emit(new Response($status, $headers, $body), $request->method);
} catch (ResponseEmissionException) {
    echo '|emission rejected';
}
