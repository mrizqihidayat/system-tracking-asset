<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter laporan = filter daftar di layar (aturan Index*Request, tanpa paginasi) + format (hanya xlsx).
 */
class IndexReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function reportType(): ReportType
    {
        return ReportType::from($this->route('type'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = match ($this->reportType()) {
            ReportType::Assets => (new IndexAssetRequest)->rules(),
            ReportType::Loans => (new IndexLoanRequest)->rules(),
            ReportType::Rooms => ['building_id' => ['nullable', 'integer']],
            ReportType::Requests => (new IndexSupplyRequestRequest)->rules(),
            ReportType::Consumables => (new IndexConsumableRequest)->rules(),
        };

        unset($rules['per_page']);

        return $rules + ['format' => ['nullable', Rule::in(['xlsx'])]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['format.in' => 'Laporan hanya tersedia dalam format Excel (xlsx).'];
    }
}
