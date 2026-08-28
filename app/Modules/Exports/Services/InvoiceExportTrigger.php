<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Exports\Jobs\ProcessExportJob;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Shared\Database\MorphMap;

/**
 * Quelles destinations reçoivent une facture clôturée, et comment les mettre en
 * file.
 *
 * **Un seul moteur d'intégration.** Le §6 interdit d'en écrire un second pour la
 * facturation : les destinations sont des `CustomerExportConfiguration`, les
 * envois des `ExportJob`, comme pour tout le reste.
 *
 * Les codes de sélection n'existaient nulle part — ni `exportType` pour la
 * facture, ni `frequency` pour la clôture. Ceux retenus sont en minuscules,
 * comme les statuts du projet, et ne sont pas des énumérations : les §34 et §35
 * l'interdisent tous deux.
 */
final readonly class InvoiceExportTrigger
{
    /** Type d'export d'une facture, dans `customer_export_configurations`. */
    public const string EXPORT_TYPE = 'invoice';

    /** Déclenchement à la clôture — le seul prévu par le §35. */
    public const string ON_CLOSED = 'on_invoice_closed';

    /**
     * Les destinations actives de cette facture.
     *
     * Le §36 fixe les quatre conditions : le client de la facture, active, du
     * bon type, et déclenchée à la clôture. Une configuration d'un autre client
     * ne peut donc jamais servir — le §113 en fait une obligation.
     *
     * @return list<CustomerExportConfiguration>
     */
    public function configurationsFor(Invoice $invoice): array
    {
        return CustomerExportConfiguration::where('customer_id', $invoice->customer_id)
            ->where('is_active', true)
            ->where('export_type', self::EXPORT_TYPE)
            ->where('frequency', self::ON_CLOSED)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /** Les envois déjà enregistrés pour cette facture. @return list<ExportJob> */
    public function jobsFor(Invoice $invoice): array
    {
        return ExportJob::where('entity_type', MorphMap::INVOICE)
            ->where('entity_id', $invoice->id)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Crée un envoi par destination, sans doublon.
     *
     * Le §30 demande qu'un second appel ne recrée rien : un envoi existant pour
     * la même configuration et la même facture est réutilisé. C'est aussi ce qui
     * permet la reprise du §135 — on relance le job, on n'en fabrique pas un
     * autre.
     *
     * @return list<ExportJob>
     */
    public function queueFor(Invoice $invoice): array
    {
        $jobs = [];

        foreach ($this->configurationsFor($invoice) as $configuration) {
            $jobs[] = ExportJob::firstOrCreate(
                [
                    'configuration_id' => $configuration->id,
                    'entity_type' => MorphMap::INVOICE,
                    'entity_id' => $invoice->id,
                ],
                [
                    // Deduit de la configuration, jamais accepte en entree : le
                    // §114 veut que les trois clients concordent.
                    'customer_id' => $configuration->customer_id,
                    'status' => 'pending',
                    'attempt_count' => 0,
                ],
            );
        }

        return $jobs;
    }

    /**
     * Met les envois en file, après le commit.
     *
     * `afterCommit` n'est pas une précaution de style : sans lui, l'ouvrier peut
     * prendre le job avant que la transaction ne soit visible, et ne trouver ni
     * la facture ni son propre enregistrement.
     *
     * @param  list<ExportJob>  $jobs
     */
    public function dispatch(array $jobs): void
    {
        foreach ($jobs as $job) {
            ProcessExportJob::dispatch($job->id)->afterCommit();
        }
    }
}
