<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validação mínima. Regras suportadas: required, email, min:N, max:N, in:a,b,c.
 * Uniqueness / regras que dependem do banco ficam no Service/Controller.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed>          $data
     * @param array<string, list<string>>   $rules
     * @param array<string, string>         $labels  rótulos amigáveis por campo
     */
    public function __construct(
        private array $data,
        array $rules,
        private array $labels = [],
    ) {
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                if (isset($this->errors[$field])) {
                    break; // um erro por campo
                }
                $this->apply($field, $rule);
            }
        }
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function apply(string $field, string $rule): void
    {
        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
        $value = trim((string) ($this->data[$field] ?? ''));
        $label = $this->labels[$field] ?? $field;

        $ok = match ($name) {
            'required' => $value !== '',
            'email'    => $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'min'      => $value === '' || mb_strlen($value) >= (int) $arg,
            'max'      => mb_strlen($value) <= (int) $arg,
            'in'       => $value === '' || in_array($value, explode(',', (string) $arg), true),
            default    => true,
        };

        if ($ok) {
            return;
        }

        $this->errors[$field] = match ($name) {
            'required' => "{$label} é obrigatório.",
            'email'    => "{$label} não é um e-mail válido.",
            'min'      => "{$label} precisa ter ao menos {$arg} caracteres.",
            'max'      => "{$label} pode ter no máximo {$arg} caracteres.",
            'in'       => "{$label} tem um valor inválido.",
            default    => "{$label} é inválido.",
        };
    }
}
