<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateTransferAction;
use App\Actions\Transactions\DeleteTransferAction;
use App\DTOs\Transactions\CreateTransferData;
use App\Http\Requests\Transactions\StoreTransferRequest;
use App\Models\Team;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function destroy(Request $request, Team $current_team, string $transfer_group_id, DeleteTransferAction $deleteTransfer): RedirectResponse
    {
        Gate::authorize('deleteTransfer', [Transaction::class, $current_team]);

        $deleteTransfer->execute($current_team, $request->user(), $transfer_group_id);

        return back()->with('success', 'La transferencia se ha eliminado correctamente'); // TODO: i18n
    }
}
