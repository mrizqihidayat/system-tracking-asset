<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class CodeGenerator
{
    public const ASSET_PREFIX = 'SHD';

    private const VOWELS = ['A', 'I', 'U', 'E', 'O'];

    /**
     * Kode singkat jenis aset dari namanya (SPEC §4), unik di asset_types.
     *
     * - Satu kata: huruf pertama + 2 konsonan berikutnya (Laptop → LPT, Kursi → KRS).
     * - Banyak kata: inisial tiap kata (maks. 3), dilengkapi konsonan berikutnya (Meja Peserta → MPS).
     * - Bentrok: tambah angka (LPT2, LPT3, ...), maksimal 5 karakter.
     */
    public function assetTypeCode(string $name, ?int $ignoreId = null): string
    {
        $base = $this->baseTypeCode($name);

        for ($suffix = 1; $suffix <= 99; $suffix++) {
            $code = $suffix === 1 ? $base : $base.$suffix;

            $exists = AssetType::query()
                ->where('code', $code)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        throw new LogicException("Kode jenis untuk \"{$name}\" tidak tersedia.");
    }

    /**
     * Kode aset berikutnya: SHD-{KODE_JENIS}-{NNN}, nomor urut per jenis.
     *
     * Nomor tidak pernah dipakai ulang (aset terhapus ikut dihitung). Wajib dipanggil di dalam
     * DB::transaction(); baris asset_types dikunci agar dua aset baru tidak mendapat nomor sama.
     */
    public function nextAssetCode(AssetType $type): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('nextAssetCode() wajib dipanggil di dalam DB::transaction().');
        }

        $type = AssetType::query()->whereKey($type->getKey())->lockForUpdate()->firstOrFail();

        $lastNumber = Asset::withTrashed()
            ->where('asset_type_id', $type->getKey())
            ->pluck('code')
            ->map(fn (string $code): int => (int) Str::afterLast($code, '-'))
            ->max() ?? 0;

        return sprintf('%s-%s-%03d', self::ASSET_PREFIX, $type->code, $lastNumber + 1);
    }

    private function baseTypeCode(string $name): string
    {
        $words = preg_split('/\s+/', trim(Str::upper(Str::ascii($name))), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter(array_map(
            fn (string $word): string => preg_replace('/[^A-Z]/', '', $word),
            $words,
        )));

        if ($words === []) {
            throw new LogicException('Nama jenis aset harus mengandung huruf.');
        }

        $initials = array_slice($words, 0, 3);
        $code = implode('', array_map(fn (string $word): string => $word[0], $initials));
        $needed = 3 - strlen($code);

        if ($needed === 0) {
            return $code;
        }

        // Pelengkap diambil dari sisa kata terakhir: utamakan konsonan, lalu huruf apa pun (urutan tetap).
        $rest = str_split(substr(end($initials), 1) ?: '');
        $picked = array_keys(array_filter($rest, fn (string $letter): bool => ! in_array($letter, self::VOWELS, true)));
        $picked = array_slice($picked, 0, $needed);

        if (count($picked) < $needed) {
            $others = array_diff(array_keys($rest), $picked);
            $picked = array_merge($picked, array_slice($others, 0, $needed - count($picked)));
            sort($picked);
        }

        foreach ($picked as $index) {
            $code .= $rest[$index];
        }

        return $code;
    }
}
