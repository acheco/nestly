<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
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
    public function store(StoreCategoryRequest $request): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): void
    {
        //
    }
}
