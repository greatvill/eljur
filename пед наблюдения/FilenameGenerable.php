<?php

namespace App\Services\POExportTraits;

use App\Models\EducationDirections;
use App\User;
use Carbon\Carbon;

trait FilenameGenerable
{
    public function getFilenameStudent(User $student): string
    {
        return sprintf(
            __('po.export_filename'),
            Carbon::now()->toDateString(),
            $student->getFio(),
            $this->format ?? 'docx'
        );
    }

    public function getFilenameGroup(User $curator): string
    {
        return sprintf(
            __('po.export_filename'),
            Carbon::now()->toDateString(),
            $this->getDirectionName($curator),
            $this->format ?? 'docx'
        );
    }

    private function getDirectionAlias(User $user)
    {
        if (!$user->direction) {
            return preg_replace('/\d/', '', $user->study_group);
        }
        return $user->direction;
    }

    private function getDirectionName(User $user)
    {
        $directionAlias = $this->getDirectionAlias($user);
        return $directionAlias
            ? (EducationDirections::DIRECTION_MAP[$directionAlias] ?? $directionAlias)
            : 'куратор - ' . $user->getShortName();
    }
}
