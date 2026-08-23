<?php

namespace App\Http\Controllers;

use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomeReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$start, $end] = $this->resolveRange(
            $request->string('range', 'today')->toString(),
            $request->input('start'),
            $request->input('end'),
        );

        // Voided/cancelled/deleted transactions never contribute: this
        // enforces status = completed at the source via the relationship,
        // rather than trusting a flag stored on the item itself.
        $items = TransactionItem::query()
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed')
                ->whereBetween('created_at', [$start, $end]))
            ->get(['product_name', 'quantity', 'subtotal', 'cogs']);

        $totalRevenue = (float) $items->sum('subtotal');
        $totalCogs = (float) $items->whereNotNull('cogs')->sum('cogs');
        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $grossMargin = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0.0;

        $byProduct = $items->groupBy('product_name')->map(function ($group) {
            $revenue = (float) $group->sum('subtotal');
            $cogs = (float) $group->whereNotNull('cogs')->sum('cogs');
            $profit = round($revenue - $cogs, 2);

            return [
                'product' => $group->first()->product_name,
                'quantity_sold' => (int) $group->sum('quantity'),
                'revenue' => round($revenue, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => $profit,
                'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0,
                'has_incomplete_cost' => $group->contains(fn ($i) => $i->cogs === null),
            ];
        })->values();

        $sort = $request->string('sort', 'revenue')->toString();

        $sorted = match ($sort) {
            'quantity' => $byProduct->sortByDesc('quantity_sold'),
            'profit_high' => $byProduct->sortByDesc('gross_profit'),
            'profit_low' => $byProduct->sortBy('gross_profit'),
            'margin' => $byProduct->sortByDesc('margin'),
            default => $byProduct->sortByDesc('revenue'),
        };

        $byProduct = $sorted->values();

        return Inertia::render('Reports/Income', [
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_cogs' => round($totalCogs, 2),
                'gross_profit' => $grossProfit,
                'gross_margin' => $grossMargin,
                'total_products_sold' => (int) $items->sum('quantity'),
                'top_profitable_product' => $byProduct->sortByDesc('gross_profit')->first()['product'] ?? null,
                'top_selling_product' => $byProduct->sortByDesc('quantity_sold')->first()['product'] ?? null,
            ],
            'byProduct' => $byProduct,
            'filters' => [  
                'range' => $request->string('range', 'today')->toString(),
                'sort' => $sort,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(string $range, ?string $start, ?string $end): array
    {
        return match ($range) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                Carbon::parse($start ?? now())->startOfDay(),
                Carbon::parse($end ?? $start ?? now())->endOfDay(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()], // 'today'
        };
    }
}