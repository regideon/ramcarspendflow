<?php

namespace App\Providers;

use App\Models\Rfp\Rfp;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short aliases for polymorphic columns (comments.commentable_type). Not enforced, because
        // roles and notifications already store full class names for User.
        Relation::morphMap([
            'rfp' => Rfp::class,
        ]);
    }
}
