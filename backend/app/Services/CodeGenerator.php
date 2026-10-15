<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCodeHistory;
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
     * - Bentrok (dipakai jenis lain sekarang atau dulu): tambah angka (LPT2, LPT3, ...), maks. 5 karakter.
     */
    public function assetTypeCode(string $name, ?int $ignoreId = null): string
    {
        $base = $this->baseTypeCode($name);

        for ($suffix = 1; $suffix <= 99; $suffix++) {
            $code = $suffix === 1 ? $base : $base.$suffix;

            $taken = AssetType::query()
                ->where('code', $code)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if (! $taken && ! $this->isTypeCodeUsedByOtherType($code, $ignoreId)) {
                return $code;
            }
        }

        throw new LogicException("Kode jenis untuk \"{$name}\" tidak tersedia.");
    }

    /**
     * Apakah kode jenis dipakai di kode aset milik jenis lain: kode aset yang ada
     * atau kode lama di asset_code_histories (dinilai dari jenis saat kode itu dipakai).
     * Mencegah kode aset baru bentrok dengan kode aset lama, mis. PRJ diubah ke PRO lalu PRJ dipakai jenis baru.
     */
    public function isTypeCodeUsedByOtherType(string $code, ?int $assetTypeId = null): bool
    {
        $pattern = self::ASSET_PREFIX.'-'.$code.'-%';
        $otherType = fn ($query) => $query->where('asset_type_id', '!=', $assetTypeId);

        return Asset::query()->where('code', 'like', $pattern)->when($assetTypeId, $otherType)->exists()
            || AssetCodeHistory::query()->where('code', 'like', $pattern)->when($assetTypeId, $otherType)->exists();
    }

    /**
     * Kode aset berikutnya: SHD-{KODE_JENIS}-{NNN}, nomor urut per jenis dari asset_types.last_number.
     *
     * Nomor tidak pernah dipakai ulang (aset dihapus atau pindah jenis tidak mengembalikan nomor).
     * Wajib di dalam DB::transaction(): baris jenis dikunci agar permintaan bersamaan tidak mendapat nomor sama.
     */
    public function nextAssetCode(AssetType $type): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('nextAssetCode() wajib dipanggil di dalam DB::transaction().');
        }

        $locked = AssetType::query()->whereKey($type->getKey())->lockForUpdate()->firstOrFail();
        $number = $locked->last_number + 1;

        AssetType::query()->whereKey($locked->getKey())->update(['last_number' => $number]);
        $type->setRawAttributes(['last_number' => $number] + $type->getAttributes(), true);

        return $this->formatAssetCode($locked->code, $number);
    }

    /**
     * Kode yang akan didapat aset berikutnya pada jenis ini, tanpa memakai nomornya.
     */
    public function previewAssetCode(AssetType $type): string
    {
        $lastNumber = (int) AssetType::query()->whereKey($type->getKey())->value('last_number');

        return $this->formatAssetCode($type->code, $lastNumber + 1);
    }

    private function formatAssetCode(string $typeCode, int $number): string
    {
        return sprintf('%s-%s-%03d', self::ASSET_PREFIX, $typeCode, $number);
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
