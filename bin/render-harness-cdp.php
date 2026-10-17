<?php
declare(strict_types=1);

/**
 * Chrome DevTools Protocol (CDP) WebSocket client.
 *
 * A minimal client that speaks CDP over a single WebSocket
 * connection. Used by bin/render-harness.php to drive
 * headless Chrome. Not a general-purpose CDP library: only
 * the methods the harness needs are implemented on the
 * calling side; the wire protocol here is generic.
 *
 * The client supports:
 *   - The RFC 6455 opening handshake (client-to-server).
 *   - Text frames, both directions, with masking.
 *   - Command dispatch: send a JSON message with an id,
 *     wait for the response with the same id.
 *   - Event subscription: wait for an event with a
 *     specific method name.
 *
 * Payload size handling: CDP messages are small (a few
 * KB), and the harness's commands are small. The framing
 * supports extended payload lengths (16-bit and 64-bit
 * length encodings), but message fragmentation is not
 * supported - CDP does not fragment.
 *
 * The class holds one socket, one buffer, and a queue of
 * received messages. It is not thread-safe; the harness
 * runs it in a single PHP process.
 *
 * Stage 015. See docs/stages/015_render-harness-plan.md.
 */
final class CdpClient
{
    /** @var resource */
    private $sock;

    /** @var string Buffered bytes not yet consumed as a message. */
    private string $buffer = '';

    /** @var array<int, array<string, mixed>> Received messages awaiting dispatch by id. */
    private array $pending = [];

    /** @var array<int, array<string, mixed>> Events received but not yet consumed. */
    private array $events = [];

    private int $nextId = 1;

    /**
     * Connect to a CDP debug endpoint and complete the
     * WebSocket handshake.
     *
     * @param string $wsUrl  e.g. ws://127.0.0.1:51946/devtools/page/ABC
     */
    public static function connect(string $wsUrl): self
    {
        $parts = parse_url($wsUrl);
        if ($parts === false
            || !isset($parts['host'], $parts['port'], $parts['path'])
        ) {
            throw new RuntimeException("Invalid ws url: {$wsUrl}");
        }

        $host = (string) $parts['host'];
        $port = (int) $parts['port'];
        $path = (string) $parts['path'];
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $sock = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            10
        );
        if ($sock === false) {
            throw new RuntimeException(
                "Socket connect failed: {$errstr} ({$errno})"
            );
        }

        $key = base64_encode(random_bytes(16));
        $handshake =
            "GET {$path} HTTP/1.1\r\n" .
            "Host: {$host}:{$port}\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Key: {$key}\r\n" .
            "Sec-WebSocket-Version: 13\r\n" .
            "\r\n";

        fwrite($sock, $handshake);

        $response = '';
        $deadline = microtime(true) + 10.0;
        while (!str_contains($response, "\r\n\r\n")) {
            $chunk = fread($sock, 1024);
            if ($chunk === '' || $chunk === false) {
                fclose($sock);
                throw new RuntimeException('Handshake response ended early');
            }
            $response .= $chunk;
            if (microtime(true) > $deadline) {
                fclose($sock);
                throw new RuntimeException('Handshake timeout');
            }
        }

        $statusLine = (string) strtok($response, "\r\n");
        if (!str_contains($statusLine, ' 101 ')) {
            fclose($sock);
            throw new RuntimeException(
                "Handshake failed: {$statusLine}"
            );
        }

        $client = new self();
        $client->sock = $sock;

        return $client;
    }

    /**
     * Send a command and wait for its response.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>  the result block
     * @throws RuntimeException when the command returns an error
     */
    public function send(string $method, array $params = []): array
    {
        $id = $this->nextId++;

        $msg = json_encode([
            'id'     => $id,
            'method' => $method,
            'params' => $params === [] ? new stdClass() : $params,
        ]);
        if ($msg === false) {
            throw new RuntimeException(
                "Failed to encode command: {$method}"
            );
        }

        $this->writeText($msg);

        $deadline = microtime(true) + 30.0;
        while (microtime(true) < $deadline) {
            $this->pump();

            if (isset($this->pending[$id])) {
                $response = $this->pending[$id];
                unset($this->pending[$id]);

                if (isset($response['error'])) {
                    $errMsg = (string) ($response['error']['message'] ?? 'unknown');
                    throw new RuntimeException(
                        "CDP error on {$method}: {$errMsg}"
                    );
                }
                return (array) ($response['result'] ?? []);
            }

            usleep(2_000);
        }

        throw new RuntimeException("Timed out waiting for {$method}");
    }

    /**
     * Wait for an event with the given name. Events received
     * before the call are checked first, so a fast event
     * that arrives during navigation is not lost.
     *
     * @return array<string, mixed>  the event's params block
     * @throws RuntimeException on timeout
     */
    public function waitForEvent(string $method, float $timeout = 5.0): array
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            foreach ($this->events as $i => $event) {
                if (($event['method'] ?? '') === $method) {
                    unset($this->events[$i]);
                    return (array) ($event['params'] ?? []);
                }
            }

            $this->pump();
            usleep(5_000);
        }

        throw new RuntimeException("Timed out waiting for {$method}");
    }

    /**
     * Drain every event currently buffered whose method
     * matches. Useful when the harness wants to consume a
     * known set of events (e.g., every Network.responseReceived
     * before a navigation completes) without pinning the
     * order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function drainEvents(string $method): array
    {
        $matching = [];
        foreach ($this->events as $i => $event) {
            if (($event['method'] ?? '') === $method) {
                $matching[] = (array) ($event['params'] ?? []);
                unset($this->events[$i]);
            }
        }
        return $matching;
    }

    public function close(): void
    {
        if (is_resource($this->sock)) {
            // Send a close frame (opcode 0x8).
            $mask = random_bytes(4);
            $frame = "\x88";
            $frame .= chr(0x80 | 0); // masked, empty payload
            $frame .= $mask;
            @fwrite($this->sock, $frame);
            @fclose($this->sock);
        }
    }

    // -----------------------------------------------------------------
    // Wire
    // -----------------------------------------------------------------

    private function pump(): void
    {
        if (!is_resource($this->sock)) {
            return;
        }

        // Read available bytes without blocking on Windows.
        $readable = [$this->sock];
        $write = null;
        $except = null;
        $n = @stream_select($readable, $write, $except, 0, 100_000);
        if ($n === false || $n === 0) {
            return;
        }

        $chunk = @fread($this->sock, 65536);
        if ($chunk === '' || $chunk === false) {
            return;
        }

        $this->buffer .= $chunk;
        $this->drain();
    }

    private function drain(): void
    {
        while (true) {
            if (strlen($this->buffer) < 2) {
                return;
            }

            $header = substr($this->buffer, 0, 2);
            $opcode = ord($header[0]) & 0x0F;
            $lenByte = ord($header[1]);
            $maskBit = ($lenByte & 0x80) !== 0;
            $len = $lenByte & 0x7F;
            $offset = 2;

            if ($len === 126) {
                if (strlen($this->buffer) < 4) {
                    return;
                }
                $len = unpack('n', substr($this->buffer, 2, 2))[1];
                $offset = 4;
            } elseif ($len === 127) {
                if (strlen($this->buffer) < 10) {
                    return;
                }
                $len = unpack('J', substr($this->buffer, 2, 8))[1];
                $offset = 10;
            }

            if ($maskBit) {
                if (strlen($this->buffer) < $offset + 4 + $len) {
                    return;
                }
                $maskKey = substr($this->buffer, $offset, 4);
                $offset += 4;
            } else {
                if (strlen($this->buffer) < $offset + $len) {
                    return;
                }
                $maskKey = '';
            }

            $payload = substr($this->buffer, $offset, $len);
            if ($maskBit) {
                $unmasked = '';
                for ($i = 0; $i < $len; $i++) {
                    $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
                }
                $payload = $unmasked;
            }

            $this->buffer = substr($this->buffer, $offset + $len);

            if ($opcode === 0x8) {
                // Close frame from server.
                @fclose($this->sock);
                return;
            }
            if ($opcode === 0x9) {
                // Ping; reply with pong.
                $this->writeControlFrame(0xA, $payload);
                continue;
            }
            if ($opcode === 0x1) {
                // Text frame; parse as JSON.
                $decoded = json_decode($payload, true);
                if (is_array($decoded)) {
                    $this->dispatch($decoded);
                }
            }
        }
    }

    private function dispatch(array $message): void
    {
        if (isset($message['id']) && is_int($message['id'])) {
            $this->pending[$message['id']] = $message;
            return;
        }
        $this->events[] = $message;
    }

    private function writeText(string $payload): void
    {
        $mask = random_bytes(4);
        $len = strlen($payload);
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        $frame = "\x81";
        if ($len < 126) {
            $frame .= chr(0x80 | $len);
        } elseif ($len < 65536) {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $len);
        }
        $frame .= $mask . $masked;

        @fwrite($this->sock, $frame);
    }

    private function writeControlFrame(int $opcode, string $payload): void
    {
        $mask = random_bytes(4);
        $len = strlen($payload);
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        $frame = chr(0x80 | $opcode);
        if ($len < 126) {
            $frame .= chr(0x80 | $len);
        } else {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        }
        $frame .= $mask . $masked;

        @fwrite($this->sock, $frame);
    }
}
