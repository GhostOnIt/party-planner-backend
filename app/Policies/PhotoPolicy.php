<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use App\Services\PermissionService;

class PhotoPolicy
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Perform pre-authorization checks.
     * Admins can do anything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null; // Fall through to specific policy methods
    }

    /**
     * Determine whether the user can view any photos for the event.
     * Photos are public, so everyone can view them.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return true; // Photos are public
    }

    /**
     * Determine whether the user can view the photo.
     * Photos are public, so everyone can view them.
     */
    public function view(User $user, Photo $photo): bool
    {
        return true; // Photos are public
    }

    /**
     * Determine whether the user can upload photos for the event.
     */
    public function upload(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'photos.upload');
    }

    /**
     * Determine whether the user can delete the photo.
     */
    public function delete(User $user, Photo $photo): bool
    {
        return $this->permissionService->userCan($user, $photo->event, 'photos.delete');
    }

    /**
     * Determine whether the user can set a photo as featured.
     */
    public function setFeatured(User $user, Photo $photo): bool
    {
        return $this->permissionService->userCan($user, $photo->event, 'photos.set_featured');
    }

    /**
     * Determine whether the user can download photos.
     * Downloads are always allowed as photos are public.
     */
    public function download(User $user, Event $event): bool
    {
        return true; // Photos are public, downloads are always allowed
    }
}
