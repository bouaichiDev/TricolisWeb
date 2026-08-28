<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceClosure;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\InvoiceExportTrigger;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clôture une facture, et met ses envois en file.
 *
 * **Clôturer est le seul déclencheur d'export.** Le §20 le dit et le §21 le
 * répète : une facture au brouillon ne part chez personne, même si une
 * configuration active existe. Créer la facture n'envoie rien.
 *
 * **Le réseau reste hors de la transaction.** Le §26 l'exige : tenir une
 * transaction MySQL ouverte pendant un appel FTP ou REST bloquerait la table le
 * temps d'un aller-retour distant, parfois d'un timeout. Les jobs sont créés
 * dans la transaction, mis en file après le commit.
 *
 * **Rejouable sans dégât.** Le §30 demande qu'un double-clic ne produise pas
 * deux envois : une facture déjà clôturée rend son état sans rien recréer, et un
 * job existant pour la même destination est réutilisé plutôt que dupliqué.
 */
final readonly class CloseInvoiceAction
{
    public function __construct(
        private InvoiceClosure $closure,
        private InvoiceExportTrigger $trigger,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @return list<ExportJob> les envois mis en file, vides si aucune destination
     */
    public function execute(Invoice $invoice, AuditContext $context): array
    {
        // Deja close : on rend l'etat sans rien refaire. Ce n'est pas une
        // erreur — c'est le second clic d'un utilisateur pressé.
        if ($this->closure->isClosed($invoice)) {
            return $this->trigger->jobsFor($invoice);
        }

        if (! $this->closure->isClosable($invoice)) {
            throw ValidationException::withMessages([
                'status' => [$invoice->lines()->exists()
                    ? 'Cette facture ne peut pas être clôturée depuis son état actuel.'
                    : 'Une facture sans ligne ne peut pas être clôturée.'],
            ]);
        }

        $jobs = DB::transaction(function () use ($invoice, $context): array {
            // Verrouillee : deux cloture simultanees ne doivent pas creer deux
            // fois les memes envois.
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($this->closure->isClosed($locked)) {
                return $this->trigger->jobsFor($locked);
            }

            $locked->forceFill(['status' => InvoiceClosure::CLOSED])->save();
            $invoice->forceFill(['status' => InvoiceClosure::CLOSED]);

            $created = $this->trigger->queueFor($locked);

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'invoice.closed',
                $locked,
                ['status' => 'draft'],
                ['status' => InvoiceClosure::CLOSED, 'exportJobs' => count($created)],
                null,
                $context->ipAddress,
            );

            return $created;
        });

        $this->trigger->dispatch($jobs);

        return $jobs;
    }

    /**
     * Les destinations actives d'une facture, avant clôture.
     *
     * Sert au dialogue de confirmation : le §52 veut que l'utilisateur sache où
     * la facture partira — ou qu'elle ne partira nulle part.
     *
     * @return list<CustomerExportConfiguration>
     */
    public function destinationsFor(Invoice $invoice): array
    {
        return $this->trigger->configurationsFor($invoice);
    }
}
