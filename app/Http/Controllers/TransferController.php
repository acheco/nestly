<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateTransferAction;
use App\DTOs\Transactions\CreateTransferData;
use App\Http\Requests\Transactions\StoreTransferRequest;
use App\Models\Team;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TransferController extends Controller
{
    public function store(StoreTransferRequest $request, Team $current_team, CreateTransferAction $createTransfer): RedirectResponse
    {
        Gate::authorize('create', [Transaction::class, $current_team]);

        $validated = $request->validated();

        $transferData = new CreateTransferData(
            from_account_id: $validated['from_account_id'],
            to_account_id: $validated['to_account_id'],
            amount: Money::fromInput($validated['amount']),
            description: $validated['description'] ?? null,
            date: CarbonImmutable::parse($validated['date']),
        );

        $createTransfer->execute($current_team, $request->user(), $transferData);

        return back()->with('success', 'La transferencia se ha creado correctamente'); // TODO: i18n
    }
}
