<?php

namespace App\Platform\Media;

final class ClamAvScanner implements Scanner
{
    public function scan(string $path): string
    {
        $socket = @stream_socket_client(
            'tcp://'.config('memoria.clamav_host').':'.config('memoria.clamav_port'),
            $errorCode, $errorMessage, 2
        );
        if (! $socket) {
            return 'unavailable';
        }
        $file = @fopen($path, 'rb');
        if (! $file) {
            fclose($socket);

            return 'unavailable';
        }
        stream_set_timeout($socket, 10);
        try {
            $write = static function ($socket, string $data): bool {
                while ($data !== '') {
                    $n = @fwrite($socket, $data);
                    if (! $n) {
                        return false;
                    }
                    $data = substr($data, $n);
                }

                return true;
            };
            if (! $write($socket, "zINSTREAM\0")) {
                return 'unavailable';
            }
            while (! feof($file)) {
                $chunk = fread($file, 65536);
                if ($chunk === false || ! $write($socket, pack('N', strlen($chunk)).$chunk)) {
                    return 'unavailable';
                }
            }
            if (! $write($socket, pack('N', 0))) {
                return 'unavailable';
            }
            $result = stream_get_contents($socket, 1024);
            if (str_contains($result ?: '', ' FOUND')) {
                return 'infected';
            }

            return trim($result ?: '', "\0\r\n ") === 'stream: OK' ? 'clean' : 'unavailable';
        } finally {
            fclose($file);
            fclose($socket);
        }
    }
}
