<?php

namespace App\Rules;

/**
 * Aturan validasi email akun (T40): format valid, maks. 150 karakter, unik di semua akun (termasuk nonaktif).
 * Email dibandingkan tanpa beda huruf besar/kecil dan disimpan huruf kecil (lihat `normalize`).
 */
class AccountEmail
{
    /**
     * @return list<mixed>
     */
    public static function rules(?int $ignoreId = null): array
    {
        return ['required', 'string', 'max:150', 'email:rfc', new UniqueCaseInsensitive('users', 'email', $ignoreId)];
    }

    public static function normalize(mixed $email): mixed
    {
        return is_string($email) ? mb_strtolower(trim($email)) : $email;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 150 karakter.',
            'email.unique' => 'Email sudah dipakai akun lain.',
        ];
    }
}
