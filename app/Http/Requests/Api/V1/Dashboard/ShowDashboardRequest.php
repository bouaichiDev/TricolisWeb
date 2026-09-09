<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Modules\Dashboard\Services\DashboardPeriod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La période demandée, et les deux façons de la demander de travers.
 *
 * **Une seule borne ne fait pas une période.** `required_with` refuse donc
 * `?from=2025-09-01` sans `to` plutôt que de compléter avec aujourd'hui : la
 * borne manquante aurait filtré sur un intervalle que personne n'a saisi, et
 * l'écran aurait affiché des chiffres justes pour une question qui n'a pas été
 * posée.
 *
 * **Une période sans fin n'en est pas une non plus.** La borne de
 * `DashboardPeriod::MAX_DAYS` est vérifiée ici, et refusée en 422 avec son
 * motif, plutôt que rognée en silence : les graphes temporels tracent une
 * colonne par jour, et un an en demanderait trois cent soixante-cinq. Rogner
 * aurait rendu un tableau de bord qui ne répond pas à la question posée, sans
 * que rien ne le dise.
 *
 * Le format est imposé — `Y-m-d`. `date` seul aurait accepté « next friday »,
 * qu'aucun sélecteur de date n'envoie mais qu'un lien recopié à la main peut
 * porter, et la réponse aurait dépendu du jour où on la relit.
 */
class ShowDashboardRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `nullable` et non `sometimes` : `sometimes` n'applique la règle
            // que si le champ est **présent**, et `required_with` ne se serait
            // donc jamais déclenché sur celui des deux qui manque — soit
            // exactement le cas qu'il est là pour attraper.
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $period = $this->period();

            if ($period !== null && $period->days() > DashboardPeriod::MAX_DAYS) {
                $validator->errors()->add('to', __(
                    'La période ne peut pas dépasser :days jours.',
                    ['days' => DashboardPeriod::MAX_DAYS],
                ));
            }
        });
    }

    /**
     * La période saisie, ou `null` — ce qui vaut « le tableau de bord habituel ».
     *
     * Lue sur l'entrée brute et non sur `validated()` : la méthode sert aussi à
     * la validation elle-même, qui n'a pas encore de données validées quand elle
     * s'exécute. Les deux dates ont alors déjà passé leur règle de format ; une
     * chaîne qui ne serait pas une date n'arrive jamais jusqu'ici avec les deux
     * bornes présentes.
     */
    public function period(): ?DashboardPeriod
    {
        $from = $this->query('from');
        $to = $this->query('to');

        if (! is_string($from) || ! is_string($to)) {
            return null;
        }

        if ($this->validator?->errors()->hasAny(['from', 'to']) === true) {
            return null;
        }

        return DashboardPeriod::fromDates($from, $to);
    }
}
