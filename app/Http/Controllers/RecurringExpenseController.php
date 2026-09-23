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
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRecurringExpenseRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(RecurringExpense $recurringExpense)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RecurringExpense $recurringExpense)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRecurringExpenseRequest $request, RecurringExpense $recurringExpense)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecurringExpense $recurringExpense)
    {
        //
    }
}
