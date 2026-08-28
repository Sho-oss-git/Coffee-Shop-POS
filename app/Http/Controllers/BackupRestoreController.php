<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BackupRestoreController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(Request $request): Response
    {
        return Inertia::render('BackupRestore', [
            'backups' => $this->backups->listBackups(),
            'lastBackup' => session('last_backup'),
        ]);
    }

    /**
     * JSON list of backups (API completeness).
     */
    public function list(Request $request)
    {
        return response()->json([
            'backups' => $this->backups->listBackups(),
        ]);
    }

    /**
     * Create a new full backup.
     */
    public function create(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->createBackup('full', 'JC66_Backup_');
            $this->log($request->user(), 'backup.created', $backup['name'], true);

            return redirect()
                ->route('backup-restore.index')
                ->with('success', 'Backup created successfully.')
                ->with('last_backup', $backup['name']);
        } catch (Throwable $e) {
            Log::error('Backup creation failed: '.$e->getMessage(), ['exception' => $e]);
            $this->log($request->user(), 'backup.created', null, false, 'Backup creation failed.');

            return redirect()
                ->route('backup-restore.index')
                ->with('error', 'Backup failed. The system could not create the backup. Please check the server configuration and try again.');
        }
    }

    /**
     * Download a backup through an authenticated controller.
     */
    public function download(Request $request, string $backup)
    {
        if (! $this->backups->backupExists($backup)) {
            abort(404, 'Backup not found.');
        }

        $this->log($request->user(), 'backup.downloaded', $backup, true);

        return $this->backups->downloadResponse($backup);
    }

    /**
     * Delete a backup permanently.
     */
    public function destroy(Request $request, string $backup): RedirectResponse
    {
        try {
            $deleted = $this->backups->deleteBackup($backup);
            $this->log($request->user(), 'backup.deleted', $backup, $deleted);

            return redirect()
                ->route('backup-restore.index')
                ->with('success', $deleted ? 'Backup deleted successfully.' : 'Backup not found.');
        } catch (Throwable $e) {
            Log::error('Backup deletion failed: '.$e->getMessage());

            return redirect()
                ->route('backup-restore.index')
                ->with('error', 'The backup could not be deleted. Please try again.');
        }
    }

    /**
     * Restore the system from an uploaded backup.
     */
    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup' => ['required', 'file', 'max:'.(BackupService::MAX_UPLOAD_BYTES / 1024)],
        ]);

        $file = $validated['backup'];

        $this->log($request->user(), 'restore.started', $file->getClientOriginalName(), true);

        try {
            $this->backups->restore($file, $request->user());
            $this->log($request->user(), 'restore.completed', $file->getClientOriginalName(), true);

            return redirect()
                ->route('backup-restore.index')
                ->with('success', 'Restore completed successfully. A safety backup of the previous state was created automatically.');
        } catch (Throwable $e) {
            Log::error('Restore failed: '.$e->getMessage(), ['exception' => $e]);
            $this->log($request->user(), 'restore.failed', $file->getClientOriginalName(), false, 'Restore failed.');

            return redirect()
                ->route('backup-restore.index')
                ->with('error', 'Restore failed. The backup could not be restored. Your current system data has not been intentionally replaced unless the restore process had already begun.');
        }
    }

    protected function log(?User $user, string $action, ?string $backupName, bool $success, ?string $message = null): void
    {
        try {
            BackupLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'backup_name' => $backupName,
                'result' => $success ? 'success' : 'failed',
                'message' => $message,
            ]);
        } catch (Throwable $e) {
            Log::error('Could not write backup log: '.$e->getMessage());
        }
    }
}
