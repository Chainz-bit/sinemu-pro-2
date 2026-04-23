<?php

namespace App\Services\Support;

class DatabaseHealthService
{
    private ?bool $isResponsive = null;

    public function isResponsive(): bool
    {
        if ($this->isResponsive !== null) {
            return $this->isResponsive;
        }

        $defaultConnection = (string) config('database.default', 'mysql');
        $connection = (array) config('database.connections.' . $defaultConnection, []);
        $driver = (string) ($connection['driver'] ?? '');

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->isResponsive = true;
            return true;
        }

        $host = (string) ($connection['host'] ?? '');
        $port = (int) ($connection['port'] ?? 3306);

        if ($host === '' || $port <= 0) {
            $this->isResponsive = false;
            return false;
        }

        if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $this->isResponsive = true;
            return true;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
        if (!is_resource($socket)) {
            $this->isResponsive = false;
            return false;
        }

        stream_set_timeout($socket, 0, 250000);
        $probe = @fread($socket, 1);
        $meta = stream_get_meta_data($socket);
        fclose($socket);

        $this->isResponsive = !($probe === false || ($probe === '' && (($meta['timed_out'] ?? false) === true)));

        return $this->isResponsive;
    }
}
