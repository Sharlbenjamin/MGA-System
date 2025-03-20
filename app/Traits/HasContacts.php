<?php

namespace App\Traits;

trait HasContacts
{
    public function primaryContact($reason = null)
    {
        $query = $this->contacts();

        if ($reason === 'Invoice' || $reason === 'Balance') {
            $query->where('name', 'Financial');
        } elseif ($reason === 'Appointment') {
            $query->where('name', 'Operation');
        }

        return $query->first();
    }
}