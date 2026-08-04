<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Http;
use PDO;

class HttpLibsqlDatabase extends Connection
{
    private string $baseUrl;

    private string $user;

    private string $password;

    public function __construct(array $config)
    {
        $url = $config['url'] ?? '';
        $parsed = parse_url($url);

        $this->baseUrl = "http://{$parsed['host']}:{$parsed['port']}";
        $this->user = $parsed['user'] ?? 'admin';
        $this->password = $parsed['pass'] ?? '';

        parent::__construct($pdo = $this->createPdo(), $config['database'] ?? 'main');
    }

    private function createPdo()
    {
        // Create a dummy PDO since Connection requires it
        // We'll override all methods to use HTTP instead
        return new class extends PDO
        {
            public function __construct() {}
        };
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->executeViaHttp($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        $this->executeViaHttp($query, $bindings);

        return true;
    }

    public function affectingStatement($query, $bindings = [])
    {
        $result = $this->executeViaHttp($query, $bindings);

        return $result['affected_row_count'] ?? 0;
    }

    public function unprepared($query)
    {
        $this->executeViaHttp($query, []);

        return true;
    }

    public function prepare($query)
    {
        return new class($query, $this)
        {
            private $query;

            private $db;

            public function __construct($query, $db)
            {
                $this->query = $query;
                $this->db = $db;
            }

            public function execute($bindings = [])
            {
                $this->db->executeViaHttp($this->query, $bindings);

                return true;
            }

            public function fetchAll()
            {
                return [];
            }

            public function rowCount()
            {
                return 0;
            }

            public function setFetchMode($mode)
            {
                return true;
            }
        };
    }

    private function executeViaHttp($query, $bindings)
    {
        $response = Http::withBasicAuth($this->user, $this->password)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->baseUrl, [
                'statements' => [
                    [
                        'q' => $this->bindParams($query, $bindings),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        if (isset($data['results'][0])) {
            return $data['results'][0];
        }

        return [];
    }

    private function bindParams($query, $bindings)
    {
        foreach ($bindings as $binding) {
            if ($binding === null) {
                $replacement = 'NULL';
            } elseif (is_numeric($binding)) {
                $replacement = $binding;
            } else {
                $replacement = "'".str_replace("'", "''", $binding)."'";
            }
            $query = preg_replace('/\?/', $replacement, $query, 1);
        }

        return $query;
    }

    public function getAttribute($attribute)
    {
        return match ($attribute) {
            PDO::ATTR_DRIVER_NAME => 'libsql',
            default => null,
        };
    }
}
