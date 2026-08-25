<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a new version of an uploadable template.
 *
 * Publishing is additive: the previous active version is retired rather than
 * replaced, so submissions filled on it stay readable. Re-uploading a
 * byte-identical file simply re-activates the existing version instead of
 * creating a duplicate.
 */
class TemplatePublisher
{
    public function __construct(private ActivityLogger $log)
    {
    }

    /**
     * @param  class-string<Model>  $modelClass  PdsTemplate, LeaveFormTemplate or LedgerTemplate
     */
    public function publish(
        string $modelClass,
        UploadedFile $file,
        string $label,
        string $directory,
        ?string $notes = null,
    ): Model {
        $checksum = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($modelClass, $file, $label, $directory, $notes, $checksum) {
            /** @var Model|null $identical */
            $identical = $modelClass::findByChecksum($checksum);

            if ($identical) {
                // Same bytes as a version we already hold — reinstate it
                // rather than shipping a second copy of the same workbook.
                $this->retireCurrent($modelClass, $identical->id);

                $identical->update([
                    'is_active' => true,
                    'superseded_at' => null,
                    'label' => $label,
                    'notes' => $notes,
                ]);

                $this->log->log(
                    $this->action($modelClass, 'reactivated'),
                    "Re-activated {$label} (v{$identical->version}) — identical to a version already on file.",
                    $identical,
                );

                return $identical;
            }

            $this->retireCurrent($modelClass);

            $template = $modelClass::create([
                'label' => $label,
                'version' => $modelClass::nextVersion(),
                'file_path' => $file->store($directory, 'public'),
                'original_filename' => $file->getClientOriginalName(),
                'checksum' => $checksum,
                'is_active' => true,
                'notes' => $notes,
                'uploaded_by' => auth()->id(),
            ]);

            $this->log->log(
                $this->action($modelClass, 'published'),
                "Published {$label} as version {$template->version}.",
                $template,
                ['version' => $template->version, 'filename' => $template->original_filename],
            );

            return $template;
        });
    }

    /** Brings an older version back into service. */
    public function activate(Model $template): void
    {
        DB::transaction(function () use ($template) {
            $this->retireCurrent($template::class, $template->id);

            $template->update(['is_active' => true, 'superseded_at' => null]);
        });

        $this->log->log(
            $this->action($template::class, 'activated'),
            "Rolled back to {$template->label} version {$template->version}.",
            $template,
        );
    }

    /** Stamps the current active version as superseded. */
    private function retireCurrent(string $modelClass, ?int $exceptId = null): void
    {
        $modelClass::where('is_active', true)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->update(['is_active' => false, 'superseded_at' => now()]);
    }

    private function action(string $modelClass, string $verb): string
    {
        return match (class_basename($modelClass)) {
            'PdsTemplate' => "pds_template.{$verb}",
            'LeaveFormTemplate' => "leave_template.{$verb}",
            'LedgerTemplate' => "ledger_template.{$verb}",
            default => "template.{$verb}",
        };
    }
}
