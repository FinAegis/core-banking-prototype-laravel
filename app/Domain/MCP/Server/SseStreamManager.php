<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events (SSE) stream manager for the MCP streamable-HTTP transport.
 *
 * Per the MCP spec (2025-11-25), `GET /mcp` opens a long-lived SSE channel that
 * the server uses to push notifications to the client. We don't yet have any
 * server-initiated notifications wired up, so this stream only emits a comment
 * heartbeat (`: heartbeat\n\n`) every `heartbeatSeconds` to keep middleboxes
 * from idling out the TCP connection. Clients are expected to reconnect after
 * the maximum lifetime elapses.
 */
final class SseStreamManager
{
    /** Hard cap on a single SSE connection so clients periodically reconnect. */
    public const MAX_LIFETIME_SECONDS = 300;

    public function open(int $heartbeatSeconds = 25): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($heartbeatSeconds): void {
            $deadline = time() + self::MAX_LIFETIME_SECONDS;

            while (time() < $deadline) {
                if (connection_aborted() === 1) {
                    break;
                }

                echo ": heartbeat\n\n";

                if (function_exists('ob_get_level') && ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();

                sleep($heartbeatSeconds);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        // Disable nginx buffering so heartbeats actually reach the client immediately.
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
