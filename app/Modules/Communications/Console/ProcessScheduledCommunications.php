<?php

declare(strict_types=1);

namespace App\Modules\Communications\Console;

use App\Modules\Communications\Actions\QueueOrderCommunicationAction;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Support\AuditContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Met en file les communications programmées dont l'heure est venue.
 *
 * Trois précautions contre le double envoi (§27) :
 *
 * 1. seules les `SCHEDULED` dont `scheduled_at <= maintenant` sont retenues ;
 * 2. la mise en file passe par `ApplyCommunicationTransition`, qui verrouille la
 *    ligne et relit le statut : deux exécutions concurrentes ne peuvent pas
 *    mettre deux fois la même communication en file ;
 * 3. une transition refusée est ignorée sans interrompre le lot — une
 *    communication annulée entre-temps ne doit pas bloquer les autres.
 *
 * Aucune table `ScheduledCommunication` n'est créée.
 */
class ProcessScheduledCommunications extends Command
{
    protected $signature = 'communications:process-scheduled {--limit=200 : Nombre maximum de communications traitées}';

    protected $description = 'Met en file les communications programmées arrivées à échéance.';

    public function handle(QueueOrderCommunicationAction $action): int
    {
        $due = OrderCommunication::where('status', CommunicationStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Carbon::now())
            ->orderBy('scheduled_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $queued = 0;

        foreach ($due as $communication) {
            try {
                $action->execute($communication, new AuditContext($communication->organization_id, null, null));
                $queued++;
            } catch (CommunicationNotEditable $exception) {
                $this->warn("{$communication->id} ignorée : {$exception->getMessage()}");
            }
        }

        $this->info("{$queued} communication(s) mise(s) en file sur {$due->count()} échue(s).");

        return self::SUCCESS;
    }
}
