<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AccountEmail;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isi/ubah email akun (T40): dipakai `PUT /user/email` (akun sendiri) dan `PUT /accounts/{account}` (staf SHD).
 */
class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => AccountEmail::normalize($this->input('email'))]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        return ['email' => AccountEmail::rules($account instanceof User ? $account->id : $this->user()->id)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountEmail::messages();
    }
}
