<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\Notification;

trait NotifiesRoles
{
    protected function notifyRoles(Notification $notification, string|array $roles): void
    {
        User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', (array) $roles))
            ->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
