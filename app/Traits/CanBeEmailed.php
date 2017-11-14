<?php

namespace App\Traits;

trait CanBeEmailed
{
    public function formatToNameForEmail()
    {
        $toNames = null;
        if (!empty($this->first_name)) {
            $toNames = [$this->first_name];
            if (!empty($this->last_name)) {
                $toNames[] = $this->last_name;
            }
            $toNames = implode(' ', $toNames);
        }
        return $toNames;
    }
}