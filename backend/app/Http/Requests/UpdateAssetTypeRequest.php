<?php

namespace App\Http\Requests;

use App\Models\AssetType;
use App\Rules\AssetTypeCodeNotUsedElsewhere;
use App\Rules\UniqueCaseInsensitive;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateAssetTypeRequest extends StoreAssetTypeRequest
{
    /**
     * Kirim field yang berubah saja; yang dikirim tidak boleh kosong.
     * Mengubah kode tidak mengubah kode aset yang sudah ada.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ignoreId = $this->ignoreId();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', new UniqueCaseInsensitive('asset_types', ignoreId: $ignoreId)],
            'code' => ['sometimes', 'required', 'string', 'regex:/^[A-Z0-9]{2,5}$/', new UniqueCaseInsensitive('asset_types', 'code', $ignoreId), new AssetTypeCodeNotUsedElsewhere($ignoreId)],
        ];
    }

    protected function ignoreId(): ?int
    {
        /** @var AssetType $assetType */
        $assetType = $this->route('asset_type');

        return $assetType->id;
    }
}
