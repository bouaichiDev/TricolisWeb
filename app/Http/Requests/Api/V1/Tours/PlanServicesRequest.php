<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Glisser une commande, ou l'un de ses services, dans une tournée.
 *
 * Les deux gestes passent par la même porte : glisser une commande revient à
 * glisser tous ses services éligibles d'un coup, et le §40 interdit de
 * demander lesquels dans une fenêtre.
 *
 * Au moins l'un des deux tableaux doit être fourni. Les deux ensemble sont
 * acceptés : ajouter une commande et un service d'une autre en un seul geste
 * n'a rien d'absurde.
 */
class PlanServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Pas de `sometimes` : il ferait sauter la regle quand les deux
            // tableaux manquent, et un appel vide passerait sans rien faire.
            'orderIds' => ['array', 'required_without:orderServiceIds'],
            'orderIds.*' => ['required', 'ulid', 'distinct'],
            'orderServiceIds' => ['array', 'required_without:orderIds'],
            'orderServiceIds.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
