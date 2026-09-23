<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LiquidsoapService
{
    private string $host;
    private int    $port;
    private string $queue;
    private int    $timeout;

    public function __construct()
    {
        $cfg = config('radio.liquidsoap');

        $this->host    = $cfg['host'];
        $this->port    = $cfg['port'];
        $this->queue   = $cfg['queue'];
        $this->timeout = $cfg['timeout'] ?? 5;
    }

    /**
     * Push an audio path/URL into the Liquidsoap request queue.
     * Returns the request ID assigned by Liquidsoap, or null on failure.
     */
    public function push(string $audioPath): ?string
    {
        $response  = $this->command("{$this->queue}.push {$audioPath}");
        $requestId = trim($response ?? '');

        if ($requestId === '' || str_starts_with($requestId, 'ERROR')) {
            Log::error('Liquidsoap push failed', ['path' => $audioPath, 'response' => $response]);
            return null;
        }

        return $requestId;
    }

    /**
     * Get the number of items currently queued.
     */
    public function queueLength(): int
    {
        $response = trim($this->command("{$this->queue}.queue") ?? '');

        return $response === '' ? 0 : count(explode(' ', $response));
    }

    /**
     * Skip the currently playing track.
     */
    public function skip(): void
    {
        $this->command("{$this->queue}.skip");
    }

    /**
     * Send a raw telnet command and return the response.
     */
    public function command(string $cmd): ?string
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (! $socket) {
            Log::error("Liquidsoap telnet connect failed: {$errstr} ({$errno})");
            return null;
        }

        stream_set_timeout($socket, $this->timeout);

        fwrite($socket, $cmd . "\n");

        $response = '';
        while (! feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) break;
            if (trim($line) === 'END') break;
            $response .= $line;
        }

        fwrite($socket, "quit\n");
        fclose($socket);

        return $response;
    }
}
