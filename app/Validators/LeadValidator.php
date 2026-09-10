<?php
declare(strict_types=1);

namespace LeadFlow\Validators;

use LeadFlow\Support\Normalizer;

final class LeadValidator
{
    public const REQUIRED = ['first_name', 'last_name', 'email', 'phone', 'state', 'zip'];

    /**
     * @return array{errors: array<string,string>, normalized: array<string, mixed>}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $out = [];

        foreach (self::REQUIRED as $f) {
            if (empty($data[$f])) $errors[$f] = "Field {$f} is required";
        }

        $out['first_name'] = trim((string)($data['first_name'] ?? ''));
        $out['last_name']  = trim((string)($data['last_name'] ?? ''));

        $email = Normalizer::email($data['email'] ?? null);
        if ($email === null && !isset($errors['email'])) $errors['email'] = 'Invalid email';
        $out['email'] = $email;

        $phone = Normalizer::phone($data['phone'] ?? null);
        if ($phone === null && !isset($errors['phone'])) $errors['phone'] = 'Invalid phone (10 digits US)';
        $out['phone'] = $phone;

        $state = Normalizer::state($data['state'] ?? null);
        if ($state === null || strlen($state) !== 2) $errors['state'] = 'Invalid state';
        $out['state'] = $state;

        $zip = Normalizer::zip($data['zip'] ?? null);
        if ($zip === null && !isset($errors['zip'])) $errors['zip'] = 'Invalid zip';
        $out['zip'] = $zip;

        $out['address'] = trim((string)($data['address'] ?? ''));
        $out['city']    = trim((string)($data['city'] ?? ''));
        $out['date_of_birth'] = Normalizer::date($data['date_of_birth'] ?? null);
        $out['employment_status'] = $data['employment_status'] ?? null;
        $out['monthly_income'] = Normalizer::money($data['monthly_income'] ?? null);
        $out['pay_frequency'] = $data['pay_frequency'] ?? null;
        $out['loan_amount'] = Normalizer::money($data['loan_amount'] ?? null);

        return ['errors' => $errors, 'normalized' => $out];
    }
}
