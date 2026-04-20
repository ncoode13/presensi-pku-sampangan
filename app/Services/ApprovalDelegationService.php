<?php

namespace App\Services;

use App\Models\ApprovalDelegation;
use App\Models\User;

class ApprovalDelegationService
{
    /**
     * Mendapatkan semua User ID yang berhak melakukan approval
     * (Approver Asli + Penerima Delegasi Aktif)
     */
    public static function getAuthorizedApprovers($originalApproverId)
    {
        $authorizedIds = [(int) $originalApproverId];

        // Cari delegasi aktif untuk user ini menggunakan scope active() yang sudah dibuat
        $delegation = ApprovalDelegation::where('user_id', $originalApproverId)
            ->active()
            ->first();

        if ($delegation) {
            $authorizedIds[] = (int) $delegation->delegate_to_id;
        }

        return array_unique($authorizedIds);
    }

    /**
     * Cek apakah user yang login berhak melakukan action pada step tertentu
     */
    public static function canPerformAction($originalApproverId)
    {
        $currentUserId = auth()->id();
        $allowedIds = self::getAuthorizedApprovers($originalApproverId);

        return in_array($currentUserId, $allowedIds);
    }

    /**
     * Mengirim notifikasi ke semua pihak yang berhak (Parallel Notification)
     */
    public static function sendParallelNotification($originalApprover, $notification)
    {
        $ids = self::getAuthorizedApprovers($originalApprover->id);
        $users = User::whereIn('id', $ids)->get();

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }
}