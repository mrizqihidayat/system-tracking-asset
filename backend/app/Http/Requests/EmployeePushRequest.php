<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

/**
 * Kiriman agen sinkron pegawai (T44): header `Authorization: Bearer {EMPLOYEE_PUSH_TOKEN}` dan tepat satu dari
 * `employees` (daftar pegawai dengan nama kolom API pegawai; boleh kosong → sinkron dibatalkan) atau `error`
 * (agen gagal mengambil dari API pegawai). Token dicek sebelum validasi, jadi body `{}` dengan token benar → 422
 * (dipakai `--dry-run` agen untuk menguji token tanpa mengubah data).
 */
class EmployeePushRequest extends FormRequest
{
    /** Pengaman jumlah baris per kiriman, jauh di atas jumlah pegawai; batas body Vercel 4,5 MB lebih dulu tercapai. */
    public const MAX_ROWS = 50000;

    public function authorize(): bool
    {
        $token = (string) config('employees.push_token');

        return $token !== '' && hash_equals($token, (string) $this->bearerToken());
    }

    /**
     * Token kosong di backend → endpoint dianggap tidak ada (404, bukan terbuka); token salah → 401.
     */
    protected function failedAuthorization(): never
    {
        $enabled = (string) config('employees.push_token') !== '';

        throw new HttpResponseException(response()->json(
            ['message' => $enabled ? 'Tidak diizinkan.' : 'Data tidak ditemukan.'],
            $enabled ? 401 : 404,
        ));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employees' => ['sometimes', 'array', 'list', 'max:'.self::MAX_ROWS],
            'error' => ['sometimes', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->has('employees') === $this->has('error')) {
                    $validator->errors()->add('employees', 'Kirim salah satu: employees (daftar pegawai) atau error.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employees.list' => 'Employees harus berupa daftar pegawai.',
        ];
    }
}
