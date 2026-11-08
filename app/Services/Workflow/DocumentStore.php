<?php

namespace App\Services\Workflow;

use App\Enums\DocumentType;
use App\Models\Project;
use App\Models\ProjectAuditLog;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores the documents the team writes (Chapters 1–3, each instrument, the complete thesis) on the private disk as
 * new, numbered versions. Nothing is overwritten: a corrected upload is a new version, and the researcher sees the
 * newest delivered one.
 */
class DocumentStore
{
    /** Formats accepted for each deliverable. */
    public const FORMATS = [
        'chapters_1_3' => ['docx', 'doc', 'pdf'],
        'instrument' => ['docx', 'doc', 'pdf', 'xlsx'],
        'complete_thesis' => ['docx', 'doc', 'pdf'],
    ];

    public function storeDeliverable(Project $project, DocumentType $type, string $label, UploadedFile $file, User $admin, ?string $variant = null, ?string $notes = null): ProjectDocument
    {
        $format = strtolower($file->getClientOriginalExtension());
        $slot = ProjectDocument::slotFor($type, $variant ?? $label);
        $path = "documents/{$project->id}/stage{$type->stage()}/".Str::uuid().'.'.$format;
        Storage::disk(config('researchhub.disk'))->put($path, fopen($file->getRealPath(), 'r'));

        $document = DB::transaction(function () use ($project, $type, $label, $slot, $format, $path, $file, $admin, $notes) {
            $version = (int) $project->documents()->where('slot', $slot)->where('kind', ProjectDocument::DELIVERABLE)->where('format', $format)->lockForUpdate()->max('version') + 1;

            return $project->documents()->create([
                'stage' => $type->stage(),
                'type' => $type,
                'slot' => $slot,
                'label' => $label,
                'kind' => ProjectDocument::DELIVERABLE,
                'version' => $version,
                'format' => $format,
                'file_path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'file_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'created_by' => $admin->id,
                'notes' => $notes,
            ]);
        });

        ProjectAuditLog::record($project, 'document.uploaded', "Uploaded {$label} v{$document->version} (.{$format})", $document, user: $admin);

        return $document;
    }

    /** Newest version of every slot and format of a stage. @return Collection<int, ProjectDocument> */
    public function latestDeliverables(Project $project, int $stage): Collection
    {
        return $this->newest($project->documents()->where('stage', $stage)->where('kind', ProjectDocument::DELIVERABLE)->get());
    }

    /** What the researcher sees: the newest delivered version of each slot and format. */
    public function latestDelivered(Project $project): Collection
    {
        return $this->newest($project->deliveredDocuments()->get());
    }

    private function newest(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProjectDocument $d) => $d->slot.'|'.$d->format)
            ->map(fn (Collection $versions) => $versions->sortByDesc('version')->first())
            ->sortBy(fn (ProjectDocument $d) => [$d->stage, $d->type === DocumentType::Instrument ? 1 : 0, $d->slot, $d->format])
            ->values();
    }
}
