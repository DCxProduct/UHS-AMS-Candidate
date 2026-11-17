<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class AccountContacts
{
    public static function email(?string $value): ?string
    {
        return filled(trim((string) $value)) ? mb_strtolower(trim($value)) : null;
    }

    public static function phone(?string $value): ?string
    {
        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return $value !== '' ? $value : null;
    }

    public static function matching(Builder $query, array $data): Builder
    {
        $email = self::email($data['email'] ?? null);
        $phone = self::phone($data['phone'] ?? null);

        return $query->where(function (Builder $contacts) use ($email, $phone): void {
            $contacts->whereRaw('1 = 0');
            if ($email !== null) {
                $column = $contacts->getQuery()->getGrammar()->wrap($contacts->getModel()->qualifyColumn('email'));
                $contacts->orWhereRaw("LOWER(TRIM({$column})) = ?", [$email]);
            }
            if ($phone !== null) {
                $column = $contacts->getQuery()->getGrammar()->wrap($contacts->getModel()->qualifyColumn('phone'));
                $separators = [' ', '-', '(', ')', '+', '.', '/', "\t", "\r", "\n"];
                $expression = "COALESCE({$column}, '')";
                foreach ($separators as $separator) {
                    $expression = "REPLACE({$expression}, ?, '')";
                }
                $contacts->orWhereRaw($expression.' = ?', [...$separators, $phone]);
            }
        });
    }
}
