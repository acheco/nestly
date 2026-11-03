<?php

namespace App\Http\Controllers;

use App\Enums\AccountVisibility;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Account::class, $current_team]);
        $request->user()->load('teamMemberships');

        $accounts = $current_team->accounts()->with('owner:id,name')->orderBy('archived')->orderBy('name')->get();

        return Inertia::render('accounts/Index', [
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'currency' => $current_team->currency,
            'can' => [
                'createTeamAccount' => Gate::allows('create', [Account::class, $current_team, AccountVisibility::Team]),
                'createPrivateAccount' => Gate::allows('create', [Account::class, $current_team, AccountVisibility::Private]),
            ],
        ]);
    }
}
