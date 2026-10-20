<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionType;
use App\Http\Requests\Concerns\ValidatesTransactionDate;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    use ValidatesTransactionDate;

    /**
     * El permiso lo decide TransactionPolicy::update() desde el controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mismas reglas que StoreTransactionRequest (TRD §3.6).
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
            'date' => $this->transactionDateRules(),
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
