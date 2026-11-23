<?php

declare(strict_types=1);

use App\Support\TransportFailure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

function curlFailure(int $errno, string $message = 'cURL error'): ConnectionException
{
    $guzzle = new ConnectException($message, new Request('POST', 'https://rpc.example'), null, ['errno' => $errno]);

    return new ConnectionException($message, 0, $guzzle);
}

it('treats a request that never left as not processed', function (int $errno): void {
    expect(TransportFailure::mayHaveBeenProcessed(curlFailure($errno)))->toBeFalse();
})->with([
    'host not resolved'  => [6],
    'connection refused' => [7],
]);

it('treats a timeout or a dropped connection as possibly processed', function (int $errno): void {
    expect(TransportFailure::mayHaveBeenProcessed(curlFailure($errno)))->toBeTrue();
})->with([
    'timed out'       => [28],
    'empty reply'     => [52],
    'receive failure' => [56],
]);

it('reads the cURL error number before the message', function (): void {
    expect(TransportFailure::mayHaveBeenProcessed(curlFailure(28, 'cURL error 7: misleading text')))->toBeTrue();
});

it('falls back to the message when there is no error number', function (): void {
    expect(TransportFailure::mayHaveBeenProcessed(new ConnectionException('cURL error 7: Failed to connect')))->toBeFalse()
        ->and(TransportFailure::mayHaveBeenProcessed(new ConnectionException('something odd')))->toBeTrue();
});

it('treats server errors and request timeouts as possibly processed, other answers as not', function (int $status, bool $expected): void {
    $exception = new RequestException(new Response(new PsrResponse($status)));

    expect(TransportFailure::mayHaveBeenProcessed($exception))->toBe($expected);
})->with([
    '500' => [500, true],
    '502' => [502, true],
    '504' => [504, true],
    '408' => [408, true],
    '400' => [400, false],
    '401' => [401, false],
    '429' => [429, false],
]);

it('treats any other failure as possibly processed', function (): void {
    expect(TransportFailure::mayHaveBeenProcessed(new RuntimeException('json decode failed')))->toBeTrue();
});
