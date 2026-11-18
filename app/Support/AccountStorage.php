<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountStorage
{
    public static function ready(): bool
    {
        return Schema::hasTable('account_identities') && Schema::hasView('account_users');
    }

    /** A read-only union; each account has exactly one authoritative store. */
    public static function createView(): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $candidate = [];
        $student = [];
        foreach (Schema::getColumnListing('users') as $column) {
            $alias = $grammar->wrap($column);
            $candidate[] = 'u.'.$alias.' AS '.$alias;
            $student[] = 'n.'.$grammar->wrap($column === 'id' ? 'auth_id' : $column).' AS '.$alias;
        }
        $candidate[] = "'candidate' AS account_store";
        $candidate[] = 'NULL AS student_record_id';
        $candidate[] = 'NULL AS student_id';
        $student[] = "'student' AS account_store";
        $student[] = 'n.id AS student_record_id';
        $student[] = 'n.student_id AS student_id';
        if (Schema::hasColumn('new_student_accounts', 'archived_at')) {
            $candidate[] = 'NULL AS archived_at';
            $student[] = 'n.archived_at AS archived_at';
        }

        DB::statement('CREATE VIEW account_users AS SELECT '.implode(', ', $candidate)
            ." FROM users u JOIN account_identities a ON a.id = u.id AND a.storage = 'candidate' UNION ALL SELECT "
            .implode(', ', $student)
            ." FROM new_student_accounts n JOIN account_identities a ON a.id = n.auth_id AND a.storage = 'student'");
    }
}
