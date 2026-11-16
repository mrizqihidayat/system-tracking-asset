<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien API pegawai (T34): satu GET ke EMPLOYEE_API_URL apa adanya (tanpa path tambahan) yang mengembalikan
 * semua pegawai, dengan API key di header. Respons boleh array langsung atau objek pembungkus (EMPLOYEE_API_DATA_KEY).
 */
class EmployeeApiClient
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException jika URL belum diatur, API tidak terjangkau, menolak (mis. 401), atau bentuk respons tidak dikenal
     */
    public function fetchAll(): array
    {
        $url = (string) config('employees.api_url');

        if ($url === '') {
            throw new RuntimeException('EMPLOYEE_API_URL belum diatur.');
        }

        $header = (string) config('employees.api_key_header');
        $key = (string) config('employees.api_key');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('employees.api_timeout'))
                ->withHeaders($key !== '' ? [$header => $key] : [])
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('API pegawai tidak bisa dihubungi.', previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('API pegawai menjawab HTTP '.$response->status().'.');
        }

        $body = $response->json();
        $dataKey = (string) config('employees.data_key');
        $rows = $dataKey !== '' && is_array($body) ? ($body[$dataKey] ?? null) : $body;

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('Bentuk respons API pegawai tidak dikenal (bukan daftar pegawai).');
        }

        return array_values(array_filter($rows, 'is_array'));
    }
}
