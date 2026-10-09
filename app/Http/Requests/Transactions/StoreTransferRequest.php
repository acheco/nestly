<?php

namespace App\Http\Requests\Transactions;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_account_id' => 'required|different:to_account_id|uuid',
            'to_account_id' => 'required|uuid',
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:'.Money::MAX,
            'description' => 'nullable|string|max:255',
            'date' => 'required|date',
        ];
    }
}
