<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

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

        return array_merge(parent::share($request), [
            'name'  => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth'  => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'role' => $request->user()->getRoleNames()->first(),
                ]) : null,
            ],
            'flash' => [
                'success'            => $request->session()->get('success'),
                'error'              => $request->session()->get('error'),
                'similarity_warning' => $request->session()->get('similarity_warning'),
                'preview'            => $request->session()->get('preview'),
                'import_summary'     => $request->session()->get('import_summary'),
                'pdf_summary'        => $request->session()->get('pdf_summary'),
            ],
        ]);
    }
}
