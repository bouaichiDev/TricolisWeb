<?php

use App\Modules\Templates\Exceptions\TemplateRenderingFailed;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateContext;
use App\Modules\Templates\Services\TemplateRenderer;

/**
 * Chemins pointés et sections — les deux capacités ajoutées en Phase 9.
 *
 * Le rendu ne touche pas la base : le modele est instancie en memoire.
 */
function document(array $attributes = []): Template
{
    return new Template(array_merge([
        'channel' => null,
        'subject_template' => null,
        'body_template' => 'Facture {{ invoice.invoiceNumber }}',
        'available_variables' => ['invoice.invoiceNumber'],
    ], $attributes));
}

function invoiceContext(): array
{
    return [
        'invoice' => [
            'invoiceNumber' => 'INV-2026-001',
            'total' => '120.50',
            'remark' => null,
            'lines' => [
                ['lineNumber' => 1, 'description' => 'Livraison', 'totalExcludingTax' => '100.00',
                    'address' => ['city' => 'Genève']],
                ['lineNumber' => 2, 'description' => 'Montage', 'totalExcludingTax' => '20.50',
                    'address' => ['city' => 'Lausanne']],
            ],
        ],
        'customer' => ['name' => 'IKEA'],
    ];
}

beforeEach(function (): void {
    $this->renderer = new TemplateRenderer(new TemplateContext);
});

describe('dotted paths', function (): void {
    it('resolves a declared dotted path', function (): void {
        $rendered = $this->renderer->renderDocument(document(), invoiceContext());

        expect($rendered->body)->toBe('Facture INV-2026-001');
    });

    it('renders an undeclared value as nothing rather than refusing, in document mode', function (): void {
        // Le contexte d'une facture porte toujours les dix-neuf chemins ; un
        // modele qui en nomme un seul est normal, pas fautif.
        $rendered = $this->renderer->renderDocument(document(), invoiceContext());

        expect($rendered->body)->not->toContain('customer');
    });

    it('still refuses a dotted path the template did not declare', function (): void {
        $this->renderer->renderDocument(
            document(['body_template' => '{{ customer.secret }}']),
            invoiceContext(),
        );
    })->throws(TemplateRenderingFailed::class, 'customer.secret');

    it('renders a null scalar as an empty string', function (): void {
        $rendered = $this->renderer->renderDocument(
            document([
                'body_template' => 'Remarque : [{{ invoice.remark }}]',
                'available_variables' => ['invoice.remark'],
            ]),
            invoiceContext(),
        );

        expect($rendered->body)->toBe('Remarque : []');
    });
});

describe('sections', function (): void {
    it('repeats a section once per line', function (): void {
        $rendered = $this->renderer->renderDocument(
            document([
                'body_template' => '{{#invoice.lines}}<tr><td>{{ invoice.lines.lineNumber }}</td>'
                    .'<td>{{ invoice.lines.description }}</td></tr>{{/invoice.lines}}',
                'available_variables' => ['invoice.lines', 'invoice.lines.lineNumber', 'invoice.lines.description'],
            ]),
            invoiceContext(),
        );

        expect($rendered->body)
            ->toBe('<tr><td>1</td><td>Livraison</td></tr><tr><td>2</td><td>Montage</td></tr>');
    });

    it('reaches the address of each line', function (): void {
        $rendered = $this->renderer->renderDocument(
            document([
                'body_template' => '{{#invoice.lines}}{{ invoice.lines.address.city }};{{/invoice.lines}}',
                'available_variables' => ['invoice.lines', 'invoice.lines.address.city'],
            ]),
            invoiceContext(),
        );

        expect($rendered->body)->toBe('Genève;Lausanne;');
    });

    it('renders nothing for an empty list', function (): void {
        $context = invoiceContext();
        $context['invoice']['lines'] = [];

        $rendered = $this->renderer->renderDocument(
            document([
                'body_template' => 'Debut{{#invoice.lines}}X{{/invoice.lines}}Fin',
                'available_variables' => ['invoice.lines'],
            ]),
            $context,
        );

        expect($rendered->body)->toBe('DebutFin');
    });

    it('refuses a section over an undeclared list', function (): void {
        $this->renderer->renderDocument(
            document([
                'body_template' => '{{#invoice.lines}}X{{/invoice.lines}}',
                'available_variables' => ['invoice.invoiceNumber'],
            ]),
            invoiceContext(),
        );
    })->throws(TemplateRenderingFailed::class, 'invoice.lines');

    it('refuses a section over something that is not a list', function (): void {
        $this->renderer->renderDocument(
            document([
                'body_template' => '{{#invoice.total}}X{{/invoice.total}}',
                'available_variables' => ['invoice.total'],
            ]),
            invoiceContext(),
        );
    })->throws(TemplateRenderingFailed::class, 'invoice.total');
});

describe('what a document may not do', function (): void {
    it('escapes html coming from the data, never from the layout', function (): void {
        $context = invoiceContext();
        $context['invoice']['invoiceNumber'] = '<script>alert(1)</script>';

        $rendered = $this->renderer->renderDocument(
            document(['body_template' => '<h1>{{ invoice.invoiceNumber }}</h1>']),
            $context,
        );

        expect($rendered->body)->toBe('<h1>&lt;script&gt;alert(1)&lt;/script&gt;</h1>')
            ->and($rendered->body)->not->toContain('<script>');
    });

    it('refuses an expression inside a placeholder', function (): void {
        $this->renderer->renderDocument(
            document(['body_template' => '{{ phpinfo() }}']),
            invoiceContext(),
        );
    })->throws(TemplateRenderingFailed::class);

    it('never interprets a value as a template of its own', function (): void {
        $context = invoiceContext();
        $context['invoice']['lines'][0]['description'] = '{{ invoice.invoiceNumber }}';

        $rendered = $this->renderer->renderDocument(
            document([
                'body_template' => '{{#invoice.lines}}{{ invoice.lines.description }}|{{/invoice.lines}}',
                'available_variables' => ['invoice.lines', 'invoice.lines.description'],
            ]),
            $context,
        );

        expect($rendered->body)->toStartWith('{{ invoice.invoiceNumber }}|');
    });
});
