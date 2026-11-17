<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Simpan pengaturan (T40). Semua kolom opsional: yang tidak dikirim tidak berubah.
 */
class UpdateSettingsRequest extends FormRequest
{
    private const TEXT_KEYS = ['site_description', 'footer_text', 'report_header', 'label_text'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (['site_title', ...self::TEXT_KEYS] as $key) {
            if ($this->has($key) && (is_string($this->input($key)) || $this->input($key) === null)) {
                $clean[$key] = trim((string) $this->input($key));
            }
        }

        foreach (['theme_primary', 'theme_sidebar'] as $key) {
            if (is_string($this->input($key))) {
                $clean[$key] = strtolower(trim($this->input($key)));
            }
        }

        $this->merge($clean);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $color = ['sometimes', 'required', 'string', 'regex:/^#[0-9a-f]{6}$/'];

        return [
            'theme_primary' => $color,
            'theme_sidebar' => $color,
            'site_title' => ['sometimes', 'required', 'string', 'max:60'],
            'site_description' => ['sometimes', 'present', 'string', 'max:200'],
            'footer_text' => ['sometimes', 'present', 'string', 'max:150'],
            'report_header' => ['sometimes', 'present', 'string', 'max:120'],
            'label_text' => ['sometimes', 'present', 'string', 'max:40'],
            'max_login_attempts' => ['sometimes', 'required', 'integer', 'between:3,10'],
            'lockout_minutes' => ['sometimes', 'required', 'integer', 'between:1,1440'],
            'password_min_length' => ['sometimes', 'required', 'integer', 'between:8,32'],
            'otp_ttl_minutes' => ['sometimes', 'required', 'integer', 'between:5,60'],
        ];
    }

    /**
     * Nilai tervalidasi; angka dikirim sebagai integer agar perbandingan dengan nilai tersimpan tepat.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $values = $this->validated();

        foreach (['max_login_attempts', 'lockout_minutes', 'password_min_length', 'otp_ttl_minutes'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = (int) $values[$key];
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme_primary.regex' => 'Warna harus berupa kode seperti #017cc2.',
            'theme_sidebar.regex' => 'Warna harus berupa kode seperti #017cc2.',
            'site_title.required' => 'Judul web wajib diisi.',
            'site_title.max' => 'Judul web maksimal 60 karakter.',
            'site_description.max' => 'Deskripsi maksimal 200 karakter.',
            'footer_text.max' => 'Teks footer maksimal 150 karakter.',
            'report_header.max' => 'Kop laporan maksimal 120 karakter.',
            'label_text.max' => 'Teks label QR maksimal 40 karakter.',
            'max_login_attempts.between' => 'Isi angka 3 sampai 10.',
            'lockout_minutes.between' => 'Isi angka 1 sampai 1440 menit.',
            'password_min_length.between' => 'Isi angka 8 sampai 32.',
            'otp_ttl_minutes.between' => 'Isi angka 5 sampai 60 menit.',
            '*.integer' => 'Isi dengan angka.',
            '*.required' => 'Wajib diisi.',
            '*.string' => 'Isi dengan teks.',
        ];
    }
}
