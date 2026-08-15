<?php

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Exceptions\TemplateRenderingFailed;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Services\CommunicationTemplateRenderer;

/**
 * Le rendu ne touche pas la base : le modele est instancie en memoire.
 */
function template(array $attributes = []): CommunicationTemplate
{
    return new CommunicationTemplate(array_merge([
        'channel' => CommunicationChannel::EMAIL,
        'subject_template' => 'Commande {{ order_number }}',
        'body_template' => 'Bonjour {{ customer_name }}.',
        'available_variables' => ['order_number', 'customer_name'],
    ], $attributes));
}

beforeEach(function (): void {
    $this->renderer = new CommunicationTemplateRenderer;
});

describe('template rendering', function (): void {
    it('replaces declared variables in subject and body', function (): void {
        $rendered = $this->renderer->render(template(), [
            'order_number' => 'CMD-42',
            'customer_name' => 'Marie',
        ]);

        expect($rendered->subject)->toBe('Commande CMD-42')
            ->and($rendered->body)->toBe('Bonjour Marie.')
            ->and($rendered->variables)->toBe(['order_number' => 'CMD-42', 'customer_name' => 'Marie']);
    });

    it('accepts spacing variations around the placeholder', function (): void {
        $rendered = $this->renderer->render(
            template(['body_template' => '{{order_number}} et {{   order_number   }}']),
            ['order_number' => 'CMD-1'],
        );

        expect($rendered->body)->toBe('CMD-1 et CMD-1');
    });

    it('leaves a null subject alone', function (): void {
        $rendered = $this->renderer->render(
            template(['channel' => CommunicationChannel::SMS, 'subject_template' => null]),
            ['customer_name' => 'Marie'],
        );

        expect($rendered->subject)->toBeNull();
    });

    it('refuses a variable absent from the declared list', function (): void {
        $this->renderer->render(
            template(['body_template' => 'Bonjour {{ secret_field }}.']),
            ['order_number' => 'CMD-1', 'customer_name' => 'Marie'],
        );
    })->throws(TemplateRenderingFailed::class, 'secret_field');

    it('refuses a value for an undeclared variable', function (): void {
        $this->renderer->render(template(), ['intruder' => 'x']);
    })->throws(TemplateRenderingFailed::class, 'intruder');

    it('refuses a declared variable left without a value', function (): void {
        $this->renderer->render(template(), ['order_number' => 'CMD-1']);
    })->throws(TemplateRenderingFailed::class, 'customer_name');

    it('refuses dotted property access', function (): void {
        $this->renderer->render(
            template(['body_template' => 'Bonjour {{ order.customer.name }}.']),
            ['order_number' => 'CMD-1', 'customer_name' => 'Marie'],
        );
    })->throws(TemplateRenderingFailed::class);

    it('refuses a php expression inside the placeholder', function (): void {
        $this->renderer->render(
            template(['body_template' => '{{ phpinfo() }}']),
            ['order_number' => 'CMD-1', 'customer_name' => 'Marie'],
        );
    })->throws(TemplateRenderingFailed::class);

    it('refuses a non scalar value', function (): void {
        $this->renderer->render(template(), [
            'order_number' => ['CMD-1'],
            'customer_name' => 'Marie',
        ]);
    })->throws(TemplateRenderingFailed::class, 'order_number');
});

describe('channel escaping', function (): void {
    it('escapes html for the email channel', function (): void {
        $rendered = $this->renderer->render(
            template([
                'subject_template' => null,
                'body_template' => 'Bonjour {{ customer_name }}.',
                'available_variables' => ['customer_name'],
            ]),
            ['customer_name' => '<script>alert(1)</script>'],
        );

        expect($rendered->body)->toBe('Bonjour &lt;script&gt;alert(1)&lt;/script&gt;.')
            ->and($rendered->body)->not->toContain('<script>');
    });

    it('does not escape for the sms channel', function (): void {
        $rendered = $this->renderer->render(
            template([
                'channel' => CommunicationChannel::SMS,
                'subject_template' => null,
                'body_template' => 'Ref {{ order_number }}',
                'available_variables' => ['order_number'],
            ]),
            ['order_number' => 'A&B'],
        );

        expect($rendered->body)->toBe('Ref A&B');
    });
});

describe('template immutability', function (): void {
    it('never modifies the template it renders', function (): void {
        $template = template();
        $before = [$template->subject_template, $template->body_template];

        $this->renderer->render($template, ['order_number' => 'CMD-1', 'customer_name' => 'Marie']);

        expect([$template->subject_template, $template->body_template])->toBe($before);
    });
});
