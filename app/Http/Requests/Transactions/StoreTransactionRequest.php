<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionType;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'account_id' => 'required|uuid',
            'category_id' => 'nullable|uuid',
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:'.Money::MAX,
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
