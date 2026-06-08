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
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'photos.view');
    }

    /**
     * Determine whether the user can view the photo.
     */
    public function view(User $user, Photo $photo): bool
    {
        if (!$this->permissionService->userCan($user, $photo->event, 'photos.view')) {
            return false;
        }

        return $photo->isApproved()
            || $this->moderate($user, $photo->event)
            || $photo->uploaded_by_user_id === $user->id;
    }

    /**
     * Determine whether the user can upload photos for the event.
     */
    public function upload(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'photos.upload');
    }

    /**
     * Determine whether the user can update photo metadata.
     */
    public function update(User $user, Photo $photo): bool
    {
        return $this->permissionService->userCan($user, $photo->event, 'photos.upload')
            || $this->moderate($user, $photo->event);
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
     * Determine whether the user can moderate photos for the event.
     */
    public function moderate(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'photos.moderate');
    }

    /**
     * Determine whether the user can download a photo.
     */
    public function download(User $user, Photo $photo): bool
    {
        return $this->view($user, $photo);
    }
}
