<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\DTOs\CreateTransactionData;
use App\DTOs\UpdateTransactionData;
use App\Enums\TransactionType;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Team;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): void
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request, Team $current_team, CreateTransactionAction $createTransaction): RedirectResponse
    {
        Gate::authorize('create', [Transaction::class, $current_team]);

        $validated = $request->validated();

        $transactionData = new CreateTransactionData(
            account_id: $validated['account_id'],
            category_id: $validated['category_id'] ?? null,
            type: TransactionType::from($validated['type']),
            amount: Money::fromInput($validated['amount']),
            date: CarbonImmutable::parse($validated['date']),
            description: $validated['description'] ?? null,
            notes: $validated['notes'] ?? null,
            transfer_group_id: null,
            recurring_id: null,
        );

        $createTransaction->execute($current_team, $request->user(), $transactionData);

        return back()->with('success', 'Transacción creada exitosamente.'); // TODO: i18n
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransactionRequest $request, Team $current_team, Transaction $transaction, UpdateTransactionAction $updateTransaction): RedirectResponse
    {
        // $transaction ya viene resuelto por $current_team->transactions()
        // (scopeBindings en la ruta): una transacción de otro team da 404.
        Gate::authorize('update', $transaction);

        $validated = $request->validated();

        $transactionData = new UpdateTransactionData(
            account_id: $validated['account_id'],
            category_id: $validated['category_id'] ?? null,
            type: TransactionType::from($validated['type']),
            amount: Money::fromInput($validated['amount']),
            date: CarbonImmutable::parse($validated['date']),
            description: $validated['description'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        $updateTransaction->execute($current_team, $request->user(), $transaction->id, $transactionData);

        return back()->with('success', 'Transacción actualizada.'); // TODO(i18n)
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction): void
    {
        //
    }
}
