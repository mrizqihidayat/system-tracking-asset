<?php

namespace App\Http\Requests;

use App\Enums\LoanStatus;
use App\Http\Requests\Concerns\ValidatesListQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLoanRequest extends FormRequest
{
    use ValidatesListQuery;

    public const DEFAULT_PER_PAGE = 15;

    /** Kunci sort => kolom (sort=kunci naik, sort=-kunci turun). */
    public const SORTABLE = ['borrow_date' => 'loans.borrow_date', 'due_date' => 'loans.due_date', 'borrower_name' => 'loans.borrower_name', 'status' => 'loans.status'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(LoanStatus::class)],
            'overdue' => ['nullable', 'boolean'],
            'due_today' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'asset_id' => ['nullable', 'integer'],
            ...$this->listRules(),
        ];
    }
}
