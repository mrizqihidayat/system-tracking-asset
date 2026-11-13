<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Staf mengubah password akun bersama (tanpa wajib ganti). */
class UpdateAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $account */
        $account = $this->route('account');

        return ['password' => app(AuthService::class)->newPasswordRules($account)];
    }
}
