<?php

namespace App\Console\Commands;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class RotateDefaultPassword extends Command
{
    protected $signature = 'auth:rotate-default-password
        {from : The existing password to replace}
        {to : The replacement password}
        {--dry-run : Report matching accounts without changing them}';

    protected $description = 'Rotate an exact password across application user accounts';

    public function handle(): int
    {
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');

        if ($from === '' || $to === '') {
            $this->error('Both the existing and replacement passwords are required.');

            return self::INVALID;
        }

        if ($from === $to) {
            $this->error('The existing and replacement passwords must be different.');

            return self::INVALID;
        }

        $counts = [];

        foreach ([User::class, SystemUser::class] as $modelClass) {
            $matched = 0;

            foreach ($modelClass::query()->select(['id', 'password'])->cursor() as $account) {
                if (! is_string($account->password) || ! Hash::check($from, $account->password)) {
                    continue;
                }

                $matched++;

                if ($this->option('dry-run')) {
                    continue;
                }

                $account->password = $to;
                $account->saveQuietly();
            }

            $counts[$modelClass] = $matched;
        }

        $total = array_sum($counts);
        $action = $this->option('dry-run') ? 'would be rotated' : 'rotated';

        $this->info(sprintf(
            'Matched accounts: %d users; %d system users; %d total. %s.',
            $counts[User::class],
            $counts[SystemUser::class],
            $total,
            ucfirst($action),
        ));

        return self::SUCCESS;
    }
}
