<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 
        'content', 
        'category_id', 
        'form_id', 
        'is_publish_to_all', 
        'target_criteria', 
        'attachment', 
        'created_by'
    ];

    protected $casts = [
        'is_publish_to_all' => 'boolean',
        'target_criteria'   => 'array',
    ];

    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute()
    {
        return $this->attachment ? \Illuminate\Support\Facades\Storage::url($this->attachment) : null;
    }

    public function category()
    {
        return $this->belongsTo(AnnouncementCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}