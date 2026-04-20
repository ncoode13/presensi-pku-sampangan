<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    
    protected $signature = 'notifications:prune-hybrid';
    protected $description = 'Membersihkan notifikasi dengan Hybrid (Read vs Unread)';

    public function handle()
    {
        $this->info('Memulai proses pembersihan notifikasi...');

        $deletedRead = \DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
        $this->info("Dihapus (Sudah Dibaca > 30 hari): {$deletedRead} data.");

        $deletedUnread = \DB::table('notifications')
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
        $this->info("Notifikasi (Belum Dibaca > 90 hari) yang dihapus: {$deletedUnread} data.");

        $this->info('Proses pembersihan notifikasi selesai.');
        return COMMAND::SUCCESS;

    }
}
