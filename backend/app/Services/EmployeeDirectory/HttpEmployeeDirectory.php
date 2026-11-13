<?php

namespace App\Services\EmployeeDirectory;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Kerangka klien API pegawai asli. Path endpoint dan bentuk respons masih asumsi; sesuaikan
 * setelah dokumentasi API INALUM tersedia (catatan T33 di docs/TASKS.md).
 * Asumsi: GET {url}/employees?search= → list atau {data: list};
 * GET {url}/employees/{nik} → objek atau {data: objek}, 404 = tidak ada.
 */
class HttpEmployeeDirectory implements EmployeeDirectory
{
    public function __construct(private string $baseUrl, private ?string $token, private int $timeout) {}

    public function search(string $query, int $limit = 10): array
    {
        $rows = $this->get('/employees', ['search' => $query])?->json() ?? [];
        $rows = is_array($rows) ? ($rows['data'] ?? $rows) : [];

        return array_slice(array_values(array_map(
            fn (array $row) => Employee::fromApi($row),
            array_filter(is_array($rows) ? $rows : [], 'is_array'),
        )), 0, $limit);
    }

    public function findByNik(string $nik): ?Employee
    {
        $row = $this->get('/employees/'.rawurlencode($nik))?->json();
        $row = is_array($row) ? ($row['data'] ?? $row) : null;

        return is_array($row) && isset($row['nik']) ? Employee::fromApi($row) : null;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function get(string $path, array $query = []): ?Response
    {
        try {
            $response = $this->client()->get($path, $query);
        } catch (ConnectionException $e) {
            throw new EmployeeDirectoryUnavailable('API pegawai tidak bisa dihubungi.', previous: $e);
        }

        if ($response->notFound()) {
            return null;
        }
        if ($response->failed()) {
            throw new EmployeeDirectoryUnavailable('API pegawai menjawab HTTP '.$response->status().'.');
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(rtrim($this->baseUrl, '/'))->acceptJson()->timeout($this->timeout);

        return $this->token ? $client->withToken($this->token) : $client;
    }
}
