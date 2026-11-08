<?php

namespace App\Policies;

use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only documents the team uploaded can be downloaded; legacy AI drafts from the previous workflow are kept in the
 * database but never served. A researcher can reach a document of their own project once it has been delivered.
 * Denials look like "not found" so undelivered documents are not even confirmed to exist.
 */
class ProjectDocumentPolicy
{
    public function download(User $user, ProjectDocument $document): Response
    {
        if ($document->kind !== ProjectDocument::DELIVERABLE) {
            return Response::denyAsNotFound();
        }
        if ($user->is_admin) {
            return Response::allow();
        }

        return $document->isDelivered() && (int) $document->project?->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
