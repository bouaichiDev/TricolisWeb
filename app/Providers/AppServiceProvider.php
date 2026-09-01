<?php

namespace App\Providers;

use App\Modules\Documents\Console\PurgeDeletedDocuments;
use App\Modules\Integrations\Services\CustomerApiContext;
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
        // Meme portee que le contexte d'organisation : le portail client le
        // remplit dans le middleware, les controleurs le relisent. Sans liaison
        // par requete, chacun recevrait une instance vierge.
        $this->app->scoped(CustomerApiContext::class);
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
