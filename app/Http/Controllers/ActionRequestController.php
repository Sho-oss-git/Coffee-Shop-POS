<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewActionRequestRequest;
use App\Http\Requests\StoreActionRequestRequest;
use App\Models\ActionRequest;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActionRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = ActionRequest::with(['requester:id,name', 'reviewer:id,name'])
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('requested_by', $request->user()->id))
            ->latest()
            ->get()
            ->map(fn (ActionRequest $actionRequest) => [
                'id' => $actionRequest->id,
                'type' => $actionRequest->type,
                'target_type' => $actionRequest->target_type,
                'target_id' => $actionRequest->target_id,
                'reason' => $actionRequest->reason,
                'status' => $actionRequest->status,
                'review_note' => $actionRequest->review_note,
                'created_at' => $actionRequest->created_at->format('M d, Y h:i A'),
                'reviewed_at' => $actionRequest->reviewed_at?->format('M d, Y h:i A'),
                'requester' => $actionRequest->requester,
                'reviewer' => $actionRequest->reviewer,
            ]);

        return Inertia::render('ActionRequests/Index', [
            'requests' => $requests,
            'canCreate' => $request->user()->isManager(),
            'canReview' => $request->user()->isAdmin(),
        ]);
    }

    public function store(StoreActionRequestRequest $request): RedirectResponse
    {
        ActionRequest::create([
            ...$request->validated(),
            'requested_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Action request submitted to Admin.');
    }

    public function review(ReviewActionRequestRequest $request, ActionRequest $actionRequest): RedirectResponse
    {
        if ($actionRequest->status !== 'pending') {
            return back()->with('error', 'This request has already been reviewed.');
        }

        DB::transaction(function () use ($actionRequest, $request): void {
            $status = $request->validated('status');

            if ($status === 'approved') {
                $this->executeApprovedRequest($actionRequest, $request->user()->id);
            }

            $actionRequest->update([
                'status' => $status,
                'review_note' => $request->validated('review_note'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'Action request reviewed successfully.');
    }

    private function executeApprovedRequest(ActionRequest $actionRequest, int $reviewerId): void
    {
        if ($actionRequest->type === 'product_deletion' && $actionRequest->target_type === 'product') {
            $product = Product::findOrFail($actionRequest->target_id);
            if ($product->image) Storage::disk('public')->delete($product->image);
            $product->delete();
            return;
        }

        if ($actionRequest->type === 'price_change' && $actionRequest->target_type === 'product') {
            Product::whereKey($actionRequest->target_id)->update([
                'price' => $actionRequest->payload['new_price'] ?? null,
            ]);
            return;
        }

        if ($actionRequest->type === 'transaction_correction' && $actionRequest->target_type === 'transaction') {
            $transaction = Transaction::findOrFail($actionRequest->target_id);
            $payload = $actionRequest->payload ?? [];

            if (($payload['action'] ?? null) === 'refund') {
                if ($transaction->status !== 'completed' || (float) $payload['refund_amount'] > (float) $transaction->total) {
                    throw new \RuntimeException('This transaction is no longer eligible for the requested refund.');
                }

                $transaction->update([
                    'status' => 'refunded',
                    'refund_amount' => $payload['refund_amount'],
                    'refund_reason' => $payload['reason'],
                    'refunded_by' => $reviewerId,
                    'refunded_at' => now(),
                ]);
            } elseif (($payload['action'] ?? null) === 'void') {
                if ($transaction->status !== 'completed') {
                    throw new \RuntimeException('This transaction is no longer eligible to be voided.');
                }

                $transaction->update([
                    'status' => 'voided',
                    'void_reason' => $payload['reason'],
                    'voided_by' => $reviewerId,
                    'voided_at' => now(),
                ]);
            }
        }
    }
}
