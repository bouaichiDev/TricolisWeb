<?php

namespace App\Providers;

use App\Modules\Documents\Console\PurgeDeletedDocuments;
use App\OpenApi\AddOrganizationHeader;
use App\OpenApi\DocumentStandardErrors;
use App\Shared\Database\MorphMap;
use App\Shared\Organizations\CurrentOrganizationContext;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentOrganizationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MorphMap::register();
        Scramble::configure()->withOperationTransformers([
            AddOrganizationHeader::class,
            DocumentStandardErrors::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeDeletedDocuments::class]);
        }
    }
}
