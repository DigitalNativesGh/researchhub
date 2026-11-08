<?php

namespace App\Policies;

use App\Models\Dataset;
use App\Models\Paper;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Users can only see and change their own projects and everything inside them (sources and data).
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    private function owns(User $user, Model $model): bool
    {
        $project = match (true) {
            $model instanceof Project => $model,
            $model instanceof Paper, $model instanceof Dataset => $model->project,
            default => null,
        };

        return $project !== null && (int) $project->user_id === (int) $user->id;
    }
}
