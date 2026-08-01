<?php

namespace App\Policies;

use App\Models\Clearance;
use App\Models\User;

class ClearancePolicy
{
    public function view(User $user, Clearance $clearance): bool
    {
        if ($user->role?->slug === 'administrator' || $user->role?->slug === 'barangay_staff') {
            return true;
        }

        $establishment = $clearance->establishment;

        return $establishment?->resident_id === $user->id
            && $establishment?->ownership_status === 'linked';
    }

    public function downloadPdf(User $user, Clearance $clearance): bool
    {
        return $this->view($user, $clearance);
    }
}
