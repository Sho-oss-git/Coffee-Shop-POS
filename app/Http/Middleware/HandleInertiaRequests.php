<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\ActionRequest;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'pending_action_requests' => fn () => $request->user()
                ? ActionRequest::where('status', 'pending')
                    ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('requested_by', $request->user()->id))
                    ->count()
                : 0,
            // Session flash data forwarded to the frontend. Inertia does not
            // expose flash() session values automatically — each key used by
            // a `->with(...)` call on a redirect response must be listed
            // here explicitly, or `page.props.flash.*` stays undefined on
            // the Vue side (this is what was blocking the transaction
            // receipt modal from ever opening).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'transaction' => fn () => $request->session()->get('transaction'),
            ],
        ];
    }
}