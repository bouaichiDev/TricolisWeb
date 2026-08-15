<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Attributs d'une modification partielle.
 *
 * Un `PATCH` ne doit écrire que les champs réellement fournis : distinguer
 * « absent » de « null » évite d'effacer une valeur que l'appelant n'a pas
 * mentionnée. Ce conteneur porte cette distinction depuis le Form Request
 * jusqu'à l'Action, sans que celle-ci touche à la Request.
 */
final readonly class PartialAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private array $attributes) {}

    /**
     * @param  array<string, mixed>  $validated  données validées, en camelCase
     * @param  array<string, string>  $mapping  colonne SQL => clé d'entrée
     */
    public static function fromValidated(array $validated, array $mapping): self
    {
        return new self(InputMapper::map($validated, $mapping));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function has(string $column): bool
    {
        return array_key_exists($column, $this->attributes);
    }

    public function get(string $column): mixed
    {
        return $this->attributes[$column] ?? null;
    }

    public function with(string $column, mixed $value): self
    {
        return new self([...$this->attributes, $column => $value]);
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }
}
