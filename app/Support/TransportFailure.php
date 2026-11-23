<?php

declare(strict_types=1);

namespace App\Support;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Tells whether a failed HTTP call may still have been processed by the server.
 *
 * A write such as broadcasting a transaction can take effect even though the
 * caller saw an error: the server may accept the request while the answer is
 * lost to a timeout or a gateway error. Callers use this to tell "it did not
 * happen" apart from "we do not know".
 */
final class TransportFailure
{
    /** cURL could not resolve the host (6) or open the connection (7), so nothing was sent. */
    private const array NOT_SENT_CURL_ERRORS = [6, 7];

    public static function mayHaveBeenProcessed(Throwable $e): bool
    {
        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status >= 500 || $status === 408;
        }

        if ($e instanceof ConnectionException) {
            $errno = self::curlErrorNumber($e);

            return $errno === null || ! in_array($errno, self::NOT_SENT_CURL_ERRORS, true);
        }

        return true;
    }

    private static function curlErrorNumber(ConnectionException $e): ?int
    {
        $previous = $e->getPrevious();
        if ($previous instanceof ConnectException) {
            $errno = $previous->getHandlerContext()['errno'] ?? null;
            if (is_int($errno)) {
                return $errno;
            }
        }

        return preg_match('/cURL error (\d+)/', $e->getMessage(), $matches) === 1 ? (int) $matches[1] : null;
    }
}
