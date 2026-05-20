<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        $duplicates = DB::select(<<<'SQL'
            select lower(email) as email_key, count(*) as total
            from users
            where email is not null
            group by lower(email)
            having count(*) > 1
            limit 5
        SQL);

        if ($duplicates !== []) {
            $emails = collect($duplicates)
                ->map(fn ($row) => "{$row->email_key} ({$row->total})")
                ->implode(', ');

            throw new RuntimeException(
                "Cannot add case-insensitive unique email index. Resolve duplicate emails first: {$emails}"
            );
        }

        DB::statement('update users set email = lower(email) where email is not null and email <> lower(email)');

        if ($driver === 'pgsql') {
            DB::statement('create unique index if not exists users_email_lower_unique on users (lower(email))');

            return;
        }

        DB::statement('create unique index if not exists users_email_lower_unique on users (lower(email))');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('drop index if exists users_email_lower_unique');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('drop index if exists users_email_lower_unique');
        }
    }
};
