<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\NotificationResource;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = $user->notifications(); 

        if ($request->filter === 'unread') {
            $query->whereNull('read_at');
        } 
        elseif ($request->filter === 'shift') {
            $query->where(function($q) {
                $q->where('data', 'ILIKE', '%shift%')
                  ->orWhere('data', 'ILIKE', '%"type"%"change_shift"%');
            });
        } 
        elseif ($request->filter === 'leave') {
            $query->where(function($q) {
                $q->where('data', 'ILIKE', '%leave%')
                  ->orWhere('data', 'ILIKE', '%"type"%"leave"%');
            });
        }
        elseif ($request->filter === 'overtime') {
            $query->where(function($q) {
                $q->where('data', 'ILIKE', '%overtime%')
                  ->orWhere('data', 'ILIKE', '%"type"%"overtime"%');
            });
        }
        elseif ($request->filter === 'attendance') {
            $query->where(function($q) {
                $q->where('data', 'ILIKE', '%attendance%')
                  ->orWhere('data', 'ILIKE', '%"type"%"attendance"%');
            });
        }
        
        $limit = $request->query('limit', 10);
        $notifications = $query->paginate($limit);

        return $this->respondSuccess(
        NotificationResource::collection($notifications)->response()->getData(true)
        );
    }

    public function getUnreadDropdown()
    {
        $notifications = auth()->user()->unreadNotifications()->limit(5)->get();
        return $this->respondSuccess($notifications);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return $this->respondSuccess(null, 'Ditandai sudah dibaca');
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return $this->respondSuccess(null, 'Semua ditandai sudah dibaca');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'exists:notifications,id',
            'action' => 'required|in:mark_read,delete'
        ]);

        $query = auth()->user()->notifications()->whereIn('id', $request->ids);

        if ($request->action === 'mark_read') {
            $query->update(['read_at' => now()]);
            $msg = 'Notifikasi terpilih ditandai dibaca.';
        } else {
            $query->delete();
            $msg = 'Notifikasi terpilih dihapus.';
        }

        return $this->respondSuccess(null, $msg);
    }
}