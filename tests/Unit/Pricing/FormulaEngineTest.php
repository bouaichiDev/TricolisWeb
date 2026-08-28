<?php

use App\Modules\Pricing\Exceptions\InvalidFormula;
use App\Modules\Pricing\Services\FormulaEvaluator;
use App\Modules\Pricing\Services\FormulaParser;
use App\Modules\Pricing\Services\FormulaTokenizer;

/**
 * Le moteur de formules tarifaires.
 *
 * Deux exigences dominent : ne jamais exécuter ce qu'on lit (§169G), et ne
 * jamais rendre un prix approximatif quand quelque chose cloche (§169I). Un
 * tarif faux ne se remarque qu'à la facture, chez le client.
 */
beforeEach(function (): void {
    $this->parser = new FormulaParser(new FormulaTokenizer);
    $this->evaluator = new FormulaEvaluator;

    $this->compute = fn (string $formula, array $variables = []): string => $this->evaluator->evaluate(
        $this->parser->parse($formula),
        $variables,
    );
});

describe('calcul', function (): void {
    /** L'exemple du §169D, celui que la demande métier donne. */
    it('calcule le tarif au poids de l’énoncé', function (): void {
        expect(($this->compute)('({P:poids} / {V:100}) * {V:25}', ['poids' => 350]))
            ->toBe('87.50');
    });

    /** La multiplication passe avant l''addition, sans parentheses. */
    it('respecte la priorité des opérateurs', function (): void {
        expect(($this->compute)('{V:2} + {V:3} * {V:4}'))->toBe('14.00');
    });

    it('respecte les parenthèses', function (): void {
        expect(($this->compute)('({V:2} + {V:3}) * {V:4}'))->toBe('20.00');
    });

    it('accepte un nombre écrit sans accolades', function (): void {
        expect(($this->compute)('{P:poids} * 2', ['poids' => 10]))->toBe('20.00');
    });

    it('accepte le moins unaire', function (): void {
        expect(($this->compute)('-{P:poids} + {V:100}', ['poids' => 30]))->toBe('70.00');
    });

    /**
     * **Le calcul ne passe pas par des flottants.** `0.1 + 0.2` y vaudrait
     * `0.30000000000000004`, et une facture qui se relit à un centime près
     * n'est plus la même facture.
     */
    it('ne dérive pas comme un flottant', function (): void {
        expect(($this->compute)('{V:0.1} + {V:0.2}'))->toBe('0.30');
    });

    /** Diviser puis multiplier : arrondir entre les deux fausserait « par
     *  tranche de 100 kg ». */
    it('n’arrondit qu’à la fin', function (): void {
        expect(($this->compute)('({P:poids} / {V:3}) * {V:3}', ['poids' => 10]))->toBe('10.00');
    });

    it('arrondit au plus proche, la moitié vers le haut', function (): void {
        expect(($this->compute)('{V:0.125} * {V:100}'))->toBe('12.50');
        expect(($this->compute)('{V:10} / {V:3}'))->toBe('3.33');
        expect(($this->compute)('{V:20} / {V:3}'))->toBe('6.67');
    });
});

describe('refus', function (): void {
    /**
     * **Le point de sécurité.** Le §169G interdit d'exécuter quoi que ce soit.
     * Le tokenizer n'accepte que ce qu'il connaît, plutôt que d'interdire des
     * motifs dangereux — liste qu'on oublie toujours de compléter.
     */
    it('refuse tout ce qui ressemble à du code', function (): void {
        foreach ([
            'eval("2+2")',
            'system("ls")',
            '{P:poids}; DROP TABLE invoices',
            'phpinfo()',
            '`whoami`',
            '$poids * 2',
            'poids.constructor',
        ] as $attempt) {
            expect(fn () => ($this->compute)($attempt, ['poids' => 1]))
                ->toThrow(InvalidFormula::class);
        }
    });

    it('refuse une variable inconnue du calcul', function (): void {
        expect(fn () => ($this->compute)('{P:inconnue} * {V:2}'))
            ->toThrow(InvalidFormula::class);
    });

    /** Une variable absente n'est pas zéro : une prestation sans poids ne
     *  coûte pas zéro franc. */
    it('refuse une valeur manquante plutôt que de la remplacer par zéro', function (): void {
        expect(fn () => ($this->compute)('{P:poids} * {V:2}', ['poids' => null]))
            ->toThrow(InvalidFormula::class);
    });

    it('refuse la division par zéro', function (): void {
        expect(fn () => ($this->compute)('{P:poids} / {V:0}', ['poids' => 10]))
            ->toThrow(InvalidFormula::class);
    });

    it('refuse une formule mal formée', function (): void {
        foreach (['({V:2} + {V:3}', '{V:2} +', '* {V:2}', '{V:2} {V:3}', ''] as $attempt) {
            expect(fn () => ($this->compute)($attempt))->toThrow(InvalidFormula::class);
        }
    });

    it('refuse une accolade mal remplie', function (): void {
        foreach (['{X:poids}', '{P:}', '{V:abc}', '{P:poids'] as $attempt) {
            expect(fn () => ($this->compute)($attempt))->toThrow(InvalidFormula::class);
        }
    });

    /** Une formule d'un mégaoctet n'est pas un tarif. */
    it('refuse une formule démesurée', function (): void {
        expect(fn () => ($this->compute)(str_repeat('{V:1}+', 200).'{V:1}'))
            ->toThrow(InvalidFormula::class);
    });

    it('refuse une imbrication sans fin', function (): void {
        expect(fn () => ($this->compute)(str_repeat('(', 30).'{V:1}'.str_repeat(')', 30)))
            ->toThrow(InvalidFormula::class);
    });

    it('refuse un montant hors de portée', function (): void {
        expect(fn () => ($this->compute)('{V:999999999} * {V:1000}'))
            ->toThrow(InvalidFormula::class);
    });
});

describe('variables détectées', function (): void {
    /** L'écran de test ne demande une valeur que pour ce qui sert. */
    it('énumère les paramètres utilisés, sans doublon', function (): void {
        $node = $this->parser->parse('({P:poids} / {V:100}) * {V:25} + {P:poids} + {P:volume}');

        expect($this->parser->variables($node))->toBe(['poids', 'volume']);
    });

    it('n’en trouve aucun dans une formule constante', function (): void {
        expect($this->parser->variables($this->parser->parse('{V:42}')))->toBe([]);
    });
});
