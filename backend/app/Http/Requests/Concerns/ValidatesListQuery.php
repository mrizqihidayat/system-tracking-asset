<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Parameter daftar yang seragam: search, per_page, sort (`kolom` naik, `-kolom` turun).
 * Kelas pemakai mendefinisikan const SORTABLE (kunci sort publik => kolom tabel) dan DEFAULT_PER_PAGE.
 */
trait ValidatesListQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @return array<string, array<mixed>>
     */
    protected function listRules(): array
    {
        $keys = array_keys(static::SORTABLE);

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in([...$keys, ...array_map(fn (string $key) => "-{$key}", $keys)])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['sort.in' => 'Urutan tidak valid.'];
    }
}
