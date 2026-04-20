<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function compressAndUpload($file, $folder)
    {
        $image = $this->manager->read($file);

        $image->scale(width: 1000);

        $filename = uniqid() . '.webp';
        $path = "$folder/$filename";

        $encoded = $image->toWebp(60);

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    public function uploadProfileWithAvatar($file, $folder)
    {
        $image = $this->manager->read($file);
        $baseFilename = uniqid();

        $mainImage = clone $image;
        $mainImage->scale(width: 800);

        $mainFilename = $baseFilename . '.webp';
        $mainPath = "$folder/$mainFilename";

        Storage::disk('public')->put($mainPath, (string) $mainImage->toWebp(80));

        $image->cover(200, 200);

        $avatarFilename = $baseFilename . '_avatar.webp';
        $avatarPath = "$folder/avatars/$avatarFilename";

        Storage::disk('public')->put($avatarPath, (string) $image->toWebp(60));

        return [
            'photo' => $mainPath,
            'avatar' => $avatarPath
        ];
    }

    public function compressAttachment($file, $folder)
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $image = $this->manager->read($file);

            $image->scale(width: 1500);

            $filename = uniqid() . '.webp';
            $path = "$folder/$filename";

            $encoded = $image->toWebp(70);

            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        }

        if ($ext === 'pdf') {
            $filename = uniqid() . '.pdf';
            $path = "$folder/$filename";

            Storage::disk('public')->putFileAs($folder, $file, $filename);

            return $path;
        }

        $filename = uniqid() . '.' . $ext;
        $path = "$folder/$filename";

        Storage::disk('public')->putFileAs($folder, $file, $filename);

        return $path;
    }
}
