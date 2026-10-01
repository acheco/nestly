<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringExpenseRequest;
use App\Http\Requests\UpdateRecurringExpenseRequest;
use App\Models\RecurringExpense;

class RecurringExpenseController extends Controller
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
    public function store(StoreRecurringExpenseRequest $request): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(RecurringExpense $recurringExpense): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RecurringExpense $recurringExpense): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRecurringExpenseRequest $request, RecurringExpense $recurringExpense): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecurringExpense $recurringExpense): void
    {
        //
    }
}
