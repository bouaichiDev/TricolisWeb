<?php

use App\Modules\Communications\Services\CommunicationRuleConditionEvaluator;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->evaluator = new CommunicationRuleConditionEvaluator;
    $this->clause = fn (string $field, string $operator, mixed $value): array => [
        'all' => [['field' => $field, 'operator' => $operator, 'value' => $value]],
    ];
});

describe('condition structure', function (): void {
    it('accepts a flat conjunction', function (): void {
        $validated = $this->evaluator->validate([
            'all' => [
                ['field' => 'order_status', 'operator' => 'eq', 'value' => 'confirmed'],
                ['field' => 'package_count', 'operator' => 'gte', 'value' => 3],
            ],
        ]);

        expect($validated['all'])->toHaveCount(2);
    });

    it('accepts null as an unconditional rule', function (): void {
        expect($this->evaluator->validate(null))->toBeNull();
    });

    it('refuses any root key other than all', function (): void {
        $this->evaluator->validate(['any' => []]);
    })->throws(ValidationException::class);

    it('refuses nesting', function (): void {
        $this->evaluator->validate(['all' => [['all' => []]]]);
    })->throws(ValidationException::class);

    it('refuses an unknown operator', function (): void {
        $this->evaluator->validate(($this->clause)('status', 'matches_regex', '.*'));
    })->throws(ValidationException::class);

    it('refuses a field name carrying a path', function (): void {
        $this->evaluator->validate(($this->clause)('order.customer.name', 'eq', 'x'));
    })->throws(ValidationException::class);

    it('refuses a non scalar value', function (): void {
        $this->evaluator->validate(($this->clause)('status', 'eq', ['a' => 'b']));
    })->throws(ValidationException::class);

    it('refuses an empty list for in', function (): void {
        $this->evaluator->validate(($this->clause)('status', 'in', []));
    })->throws(ValidationException::class);
});

describe('condition evaluation', function (): void {
    it('evaluates the eight operators', function (): void {
        $cases = [
            ['eq', 'confirmed', 'confirmed', true],
            ['eq', 'confirmed', 'draft', false],
            ['neq', 'draft', 'confirmed', true],
            ['gt', 2, 3, true],
            ['gt', 3, 3, false],
            ['gte', 3, 3, true],
            ['lt', 5, 3, true],
            ['lte', 3, 3, true],
        ];

        foreach ($cases as [$operator, $expected, $fact, $outcome]) {
            $conditions = $this->evaluator->validate(($this->clause)('value', $operator, $expected));

            expect($this->evaluator->passes($conditions, ['value' => $fact]))->toBe($outcome);
        }
    });

    it('evaluates list operators', function (): void {
        $in = $this->evaluator->validate(($this->clause)('status', 'in', ['confirmed', 'ready']));
        $notIn = $this->evaluator->validate(($this->clause)('status', 'not_in', ['cancelled']));

        expect($this->evaluator->passes($in, ['status' => 'ready']))->toBeTrue()
            ->and($this->evaluator->passes($in, ['status' => 'draft']))->toBeFalse()
            ->and($this->evaluator->passes($notIn, ['status' => 'ready']))->toBeTrue()
            ->and($this->evaluator->passes($notIn, ['status' => 'cancelled']))->toBeFalse();
    });

    it('requires every clause of the conjunction', function (): void {
        $conditions = $this->evaluator->validate([
            'all' => [
                ['field' => 'status', 'operator' => 'eq', 'value' => 'confirmed'],
                ['field' => 'count', 'operator' => 'gte', 'value' => 3],
            ],
        ]);

        expect($this->evaluator->passes($conditions, ['status' => 'confirmed', 'count' => 5]))->toBeTrue()
            ->and($this->evaluator->passes($conditions, ['status' => 'confirmed', 'count' => 1]))->toBeFalse();
    });

    it('fails when a fact is missing rather than assuming it', function (): void {
        $conditions = $this->evaluator->validate(($this->clause)('status', 'eq', 'confirmed'));

        expect($this->evaluator->passes($conditions, []))->toBeFalse();
    });

    it('passes when there is no condition at all', function (): void {
        expect($this->evaluator->passes(null, []))->toBeTrue()
            ->and($this->evaluator->passes(['all' => []], []))->toBeTrue();
    });
});
