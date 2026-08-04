<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use PDO;

class HttpLibsqlDatabase
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
    }

    public function select($query, $bindings = [])
    {
        return $this->runHttpQuery($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        $this->runHttpQuery($query, $bindings);

        return true;
    }

    public function affectingStatement($query, $bindings = [])
    {
        $result = $this->runHttpQuery($query, $bindings);

        return $result['affected_row_count'] ?? 0;
    }

    public function unprepared($query)
    {
        $this->runHttpQuery($query, []);

        return true;
    }

    public function prepare($query)
    {
        $baseUrl = $this->baseUrl;
        $user = $this->user;
        $password = $this->password;

        return new class($query, $baseUrl, $user, $password)
        {
            private $query;

            private $baseUrl;

            private $user;

            private $password;

            public function __construct($query, $baseUrl, $user, $password)
            {
                $this->query = $query;
                $this->baseUrl = $baseUrl;
                $this->user = $user;
                $this->password = $password;
            }

            public function execute($bindings = [])
            {
                $response = Http::withBasicAuth($this->user, $this->password)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->baseUrl, [
                        'statements' => [
                            [
                                'q' => $this->bindParams($this->query, $bindings),
                            ],
                        ],
                    ]);

                if ($response->failed()) {
                    throw new \Exception("HTTP {$response->status()}: {$response->body()}");
                }

                return true;
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

    private function runHttpQuery($query, $bindings)
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
