<?php

/*
|--------------------------------------------------------------------------
| Pesan validasi (Bahasa Indonesia, singkat)
|--------------------------------------------------------------------------
|
| Aturan yang belum ada di sini jatuh ke fallback "en" bawaan Laravel.
|
*/

return [
    'accepted' => ':Attribute harus disetujui.',
    'after' => ':Attribute harus setelah :date.',
    'after_or_equal' => ':Attribute minimal :date.',
    'array' => ':Attribute harus berupa daftar.',
    'before' => ':Attribute harus sebelum :date.',
    'before_or_equal' => ':Attribute maksimal :date.',
    'between' => [
        'numeric' => ':Attribute harus antara :min dan :max.',
        'file' => ':Attribute harus antara :min dan :max KB.',
        'string' => ':Attribute harus :min–:max karakter.',
        'array' => ':Attribute harus berisi :min–:max item.',
    ],
    'boolean' => ':Attribute harus ya atau tidak.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'date_format' => ':Attribute harus berformat :format.',
    'distinct' => ':Attribute tidak boleh duplikat.',
    'email' => ':Attribute harus berupa email yang valid.',
    'enum' => ':Attribute tidak valid.',
    'exists' => ':Attribute tidak ditemukan.',
    'file' => ':Attribute harus berupa file.',
    'filled' => ':Attribute wajib diisi.',
    'gt' => [
        'numeric' => ':Attribute harus lebih dari :value.',
    ],
    'gte' => [
        'numeric' => ':Attribute minimal :value.',
    ],
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'lt' => [
        'numeric' => ':Attribute harus kurang dari :value.',
    ],
    'lte' => [
        'numeric' => ':Attribute maksimal :value.',
    ],
    'max' => [
        'numeric' => ':Attribute maksimal :max.',
        'file' => ':Attribute maksimal :max KB.',
        'string' => ':Attribute maksimal :max karakter.',
        'array' => ':Attribute maksimal :max item.',
    ],
    'mimes' => ':Attribute harus berformat: :values.',
    'mimetypes' => ':Attribute harus berformat: :values.',
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'file' => ':Attribute minimal :min KB.',
        'string' => ':Attribute minimal :min karakter.',
        'array' => ':Attribute minimal :min item.',
    ],
    'not_in' => ':Attribute tidak valid.',
    'numeric' => ':Attribute harus berupa angka.',
    'present' => ':Attribute harus ada.',
    'prohibited' => ':Attribute tidak boleh diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'required_if' => ':Attribute wajib diisi jika :other :value.',
    'required_unless' => ':Attribute wajib diisi kecuali :other :values.',
    'required_with' => ':Attribute wajib diisi jika :values diisi.',
    'required_without' => ':Attribute wajib diisi jika :values kosong.',
    'same' => ':Attribute harus sama dengan :other.',
    'size' => [
        'numeric' => ':Attribute harus :size.',
        'string' => ':Attribute harus :size karakter.',
        'array' => ':Attribute harus berisi :size item.',
    ],
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah digunakan.',
    'uploaded' => ':Attribute gagal diunggah.',

    'attributes' => [
        'name' => 'nama',
        'code' => 'kode',
        'category_id' => 'kategori',
        'building_id' => 'gedung',
        'room_id' => 'ruangan',
        'search' => 'pencarian',
    ],
];
