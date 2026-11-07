<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Services\PurchaseNoteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar Belanja Kering: dry-goods demand of every Dapur not yet shipped,
 * minus Gudang stock — what the koperasi still has to buy (e.g. Saturday).
 */
class ShoppingListController extends Controller
{
    public function index(Request $request, PurchaseNoteService $service): Response
    {
        $validated = $request->validate(['until' => ['nullable', 'date_format:Y-m-d']]);
        $until = $validated['until'] ?? now('Asia/Jakarta')->addDays(7)->toDateString();

        return Inertia::render('purchasing/shopping-list/index', [
            'until' => $until,
            'rows' => $service->shoppingList($until),
        ]);
    }
}
