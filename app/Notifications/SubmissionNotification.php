<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubmissionNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $message;
    protected $link;
    protected $type;
    protected $image;

    /**
     * Create a new notification instance.
     *
     * @param string $title Judul Notif (Contoh: "Pengajuan Cuti")
     * @param string $message Isi Pesan (Contoh: "Budi mengajukan cuti tahunan")
     * @param string $link Link tujuan saat diklik (Contoh: "/leave/detail/1")
     * @param string $type Tipe untuk icon/warna di FE (Contoh: "leave", "shift", "overtime")
     * @param string $image URL gambar notifikasi (opsional)
     */
    public function __construct($title, $message, $link, $type='info', $image = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
        $this->type = $type;
        $this->image = $image;
    }
    public function via($notifiable)
    {
        return ['database'];
    }
    public function toDatabase($notifiable)
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'link'    => $this->link,
            'type'    => $this->type,
            'image'   => $this->image,
            'created_at' => now(), 
        ];
    }
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'type' => $this->type,
            'image' => $this->image,
            'created_at' => now(),
        ];
    }
}
