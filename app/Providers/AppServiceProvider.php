<?php

namespace App\Providers;

use App\Models\Complaint;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.proprietor', function ($view) {
            $view->with('unreadComplaintCount', once(function () {
                try {
                    return Complaint::whereNull('read_at')->count();
                } catch (QueryException $exception) {
                    // Keep other proprietor pages usable while the existing read_at migration is pending.
                    if (! Schema::hasColumn('complaints', 'read_at')) {
                        return 0;
                    }

                    throw $exception;
                }
            }));
        });
    }
}
