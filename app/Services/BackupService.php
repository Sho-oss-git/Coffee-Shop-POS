<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use ZipArchive;

class BackupService
{
    /**
     * Maximum accepted upload size for a restore file (bytes). 200 MB.
     */
    public const MAX_UPLOAD_BYTES = 200 * 1024 * 1024;

    public function __construct()
    {
        $this->ensureBackupsDirectory();
    }

    /**
     * Absolute path to the backups storage directory.
     */
    public function backupsDirectory(): string
    {
        return storage_path('app/' . Config::get('backup.path', 'backups'));
    }

    protected function ensureBackupsDirectory(): void
    {
        $dir = $this->backupsDirectory();
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755, true);
        }
    }

    /**
     * Database credentials taken live from the Laravel configuration so they
     * are never hard-coded in this class.
     */
    protected function databaseConfig(): array
    {
        $connection = Config::get('database.default');
        $config = Config::get("database.connections.{$connection}", []);

        return [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? 3306),
            'database' => $config['database'] ?? '',
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
        ];
    }

    /**
     * Resolve a MySQL binary (mysqldump / mysql) safely:
     *   1. explicit override (config or env)
     *   2. common XAMPP / system locations
     *   3. system PATH via `where` / `which`
     */
    protected function resolveBinary(string $configKey, string $envKey, string $binary, array $candidates): ?string
    {
        $explicit = Config::get($configKey) ?: env($envKey);
        if ($explicit && @is_executable($explicit)) {
            return $explicit;
        }

        foreach ($candidates as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }

        $command = PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary];
        $result = Process::timeout(10)->run($command);

        if ($result->successful()) {
            $found = trim(explode("\n", $result->output())[0]);
            if ($found !== '' && @is_executable($found)) {
                return $found;
            }
        }

        return null;
    }

    protected function mysqldumpBinary(): ?string
    {
        return $this->resolveBinary(
            'backup.mysqldump_path',
            'MYSQLDUMP_PATH',
            'mysqldump',
            [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\xampp\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/opt/lampp/bin/mysqldump',
            ],
        );
    }

    protected function mysqlBinary(): ?string
    {
        return $this->resolveBinary(
            'backup.mysql_path',
            'MYSQL_PATH',
            'mysql',
            [
                'C:\\xampp\\mysql\\bin\\mysql.exe',
                'C:\\xampp\\bin\\mysql.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysql.exe',
                '/usr/bin/mysql',
                '/usr/local/bin/mysql',
                '/opt/lampp/bin/mysql',
            ],
        );
    }

    /**
     * Produce a MySQL dump of the entire configured database into $outputFile.
     *
     * Uses the application's own PDO connection (pure-PHP dump). This is the
     * reliable path in every environment — including when the MySQL client
     * tools (mysqldump/mysql) cannot be spawned by the web server process
     * (e.g. the Windows "Can't create TCP/IP socket (10106)" error seen under
     * `php artisan serve`). The CLI-based dumpers remain available in
     * app/Services for environments where the native client tools work.
     */
    protected function dumpDatabase(string $outputFile): void
    {
        $this->dumpDatabaseViaPdo($outputFile);
    }

    /**
     * Database dump using the mysqldump CLI client.
     */
    protected function dumpDatabaseViaCli(string $mysqldump, string $outputFile): void
    {
        $db = $this->databaseConfig();

        $command = [
            $mysqldump,
            '--host='.$db['host'],
            '--port='.$db['port'],
            '-u'.$db['username'],
        ];

        if ($db['password'] !== '' && $db['password'] !== null) {
            $command[] = '-p'.$db['password'];
        }

        $command[] = '--single-transaction';
        $command[] = '--routines';
        $command[] = '--triggers';
        $command[] = '--events';
        $command[] = '--no-tablespaces';
        $command[] = '--skip-lock-tables';
        $command[] = $db['database'];

        $handle = fopen($outputFile, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the temporary dump file for writing.');
        }

        $result = Process::timeout(600)
            ->run($command, function (string $type, string $buffer) use ($handle) {
                fwrite($handle, $buffer);
            });

        fclose($handle);

        if (! $result->successful()) {
            throw new \RuntimeException('The database dump process failed: '.$result->errorOutput());
        }
    }

    /**
     * Database dump using the application's own PDO connection. Works anywhere
     * the app can reach its database, independent of external MySQL client tools.
     */
    protected function dumpDatabaseViaPdo(string $outputFile): void
    {
        $pdo = DB::connection()->getPdo();
        $dbName = $this->databaseConfig()['database'];

        $tables = $pdo
            ->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ".$pdo->quote($dbName)." AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")
            ->fetchAll(PDO::FETCH_COLUMN);

        $sql = "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n/*!40101 SET NAMES utf8mb4 */;\n\n";

        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC);
            $createSql = $create['Create Table'] ?? $create['Create View'] ?? null;
            if (! $createSql) {
                continue;
            }

            $sql .= 'DROP TABLE IF EXISTS `'.$table."`;\n";
            $sql .= $createSql.";\n\n";

            $stmt = $pdo->query('SELECT * FROM `'.$table.'`');
            $cols = [];
            $colCount = $stmt->columnCount();
            for ($i = 0; $i < $colCount; $i++) {
                $meta = $stmt->getColumnMeta($i);
                $cols[] = '`'.$meta['name'].'`';
            }
            if ($colCount === 0) {
                continue;
            }
            $colList = implode(',', $cols);
            $rows = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vals = [];
                foreach ($row as $value) {
                    $vals[] = $value === null ? 'NULL' : $pdo->quote($value);
                }
                $rows[] = '('.implode(',', $vals).')';

                if (count($rows) >= 300) {
                    $sql .= 'INSERT INTO `'.$table.'` ('.$colList.') VALUES '.implode(",\n", $rows).";\n";
                    $rows = [];
                }
            }

            if ($rows !== []) {
                $sql .= 'INSERT INTO `'.$table.'` ('.$colList.') VALUES '.implode(",\n", $rows).";\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if (file_put_contents($outputFile, $sql) === false) {
            throw new \RuntimeException('Could not write the database dump file.');
        }
    }

    /**
     * Build the backup-info.json metadata. No secrets are included.
     */
    protected function buildMetadata(string $type): array
    {
        return [
            'application' => Config::get('backup.application_name', 'JC66 Coffee Shop Management System'),
            'backup_date' => now()->toIso8601String(),
            'database' => $this->databaseConfig()['database'],
            'version' => Config::get('backup.version', '1.0'),
            'backup_type' => $type,
            'format' => Config::get('backup.format', 'jc66-backup'),
        ];
    }

    /**
     * Recursively add a directory's contents into the zip under a prefix.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipPrefix, array $excludeDirs = []): void
    {
        if (! is_dir($sourceDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $relative = ltrim(substr($item->getPathname(), strlen($sourceDir)), DIRECTORY_SEPARATOR);
            $relative = str_replace('\\', '/', $relative);

            // Skip excluded top-level directories (e.g. build assets, storage symlink).
            $top = explode('/', $relative)[0] ?? '';
            if ($item->isDir() && in_array($top, $excludeDirs, true)) {
                continue;
            }
            if (in_array($top, $excludeDirs, true)) {
                continue;
            }

            $zipPath = rtrim($zipPrefix, '/').'/'.$relative;

            if ($item->isDir()) {
                $zip->addEmptyDir($zipPath);
            } else {
                $zip->addFile($item->getPathname(), $zipPath);
            }
        }
    }

    /**
     * Create a full backup (or a safety/pre-restore backup).
     *
     * @return array{name:string, path:string, size:int, size_human:string, date:string, type:string}
     */
    public function createBackup(string $type = 'full', string $prefix = 'JC66_Backup_'): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $name = $prefix.$timestamp.'.zip';
        $zipPath = $this->backupsDirectory().DIRECTORY_SEPARATOR.$name;

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'jc66_backup_'.$timestamp;
        File::ensureDirectoryExists($tempDir, 0755, true);

        try {
            $sqlFile = $tempDir.DIRECTORY_SEPARATOR.'database.sql';
            $this->dumpDatabase($sqlFile);

            $metadataFile = $tempDir.DIRECTORY_SEPARATOR.'backup-info.json';
            File::put($metadataFile, json_encode($this->buildMetadata($type), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create the backup archive.');
            }

            $zip->addFile($sqlFile, 'database.sql');
            $zip->addFile($metadataFile, 'backup-info.json');

            // User-uploaded application files.
            $this->addDirectoryToZip($zip, storage_path('app/public'), 'storage');
            $this->addDirectoryToZip($zip, public_path(), 'public', ['build', 'storage']);

            $zip->close();

            if (! file_exists($zipPath)) {
                throw new \RuntimeException('The backup archive was not created.');
            }

            $this->applyRetention();

            return $this->describeBackup($name);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * A safety backup of the current system taken automatically before a restore.
     */
    public function createSafetyBackup(User $user): string
    {
        $backup = $this->createBackup('pre-restore', 'JC66_PreRestore_');

        return $backup['name'];
    }

    /**
     * Delete old backups so only the newest N remain (manual deletion always works).
     */
    protected function applyRetention(): void
    {
        $retention = (int) Config::get('backup.retention', 0);
        if ($retention <= 0) {
            return;
        }

        $backups = $this->listBackups();
        if (count($backups) <= $retention) {
            return;
        }

        $excess = array_slice($backups, $retention);
        foreach ($excess as $old) {
            $this->deleteBackup($old['name']);
        }
    }

    /**
     * List all backups, newest first.
     *
     * @return array<int, array{name:string, size:int, size_human:string, date:string, type:string}>
     */
    public function listBackups(): array
    {
        $dir = $this->backupsDirectory();
        if (! is_dir($dir)) {
            return [];
        }

        $files = File::files($dir);
        $backups = [];

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'zip') {
                continue;
            }
            $backups[] = $this->describeBackup($file->getFilename());
        }

        usort($backups, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    protected function describeBackup(string $name): array
    {
        $path = $this->backupsDirectory().DIRECTORY_SEPARATOR.$name;
        $size = file_exists($path) ? filesize($path) : 0;

        // Type is encoded in the prefix of the file name.
        $type = str_starts_with($name, 'JC66_PreRestore_') ? 'pre-restore' : 'full';

        return [
            'name' => $name,
            'path' => $path,
            'size' => $size,
            'size_human' => $this->humanSize($size),
            'date' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : '',
            'type' => $type,
        ];
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 1).' '.$units[$pow];
    }

    public function backupExists(string $name): bool
    {
        $path = $this->backupsDirectory().DIRECTORY_SEPARATOR.basename($name);

        return file_exists($path);
    }

    public function backupPath(string $name): string
    {
        return $this->backupsDirectory().DIRECTORY_SEPARATOR.basename($name);
    }

    /**
     * Download response for a backup (must go through authenticated controller).
     */
    public function downloadResponse(string $name)
    {
        $path = $this->backupPath($name);
        if (! file_exists($path)) {
            abort(404, 'Backup not found.');
        }

        return response()->download($path, $name, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function deleteBackup(string $name): bool
    {
        $path = $this->backupPath($name);
        if (! file_exists($path)) {
            return false;
        }

        return File::delete($path);
    }

    /**
     * Validate an uploaded restore file. Returns structured result.
     *
     * @return array{valid:bool, error:?string}
     */
    public function validateUploadedBackup(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return ['valid' => false, 'error' => 'The uploaded file is not valid.'];
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return ['valid' => false, 'error' => 'Only .zip backup files are accepted.'];
        }

        $mime = strtolower($file->getMimeType());
        $allowedMimes = ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'];
        if (! in_array($mime, $allowedMimes, true)) {
            return ['valid' => false, 'error' => 'The file does not appear to be a valid ZIP archive.'];
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return ['valid' => false, 'error' => 'The backup file is too large (max 200 MB).'];
        }

        $zip = new ZipArchive();
        $open = $zip->open($file->getRealPath(), ZipArchive::RDONLY);
        if ($open !== true) {
            return ['valid' => false, 'error' => 'The ZIP archive could not be opened or is corrupted.'];
        }

        $hasSql = false;
        $hasMeta = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }

            // Reject path traversal / absolute paths anywhere in the archive.
            if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_contains($entry, '\\')) {
                $zip->close();

                return ['valid' => false, 'error' => 'The archive contains unsafe file paths and was rejected.'];
            }

            if ($entry === 'database.sql') {
                $hasSql = true;
            }
            if ($entry === 'backup-info.json') {
                $hasMeta = true;
            }
        }

        if (! $hasSql || ! $hasMeta) {
            $zip->close();

            return ['valid' => false, 'error' => 'The archive is missing required files (database.sql or backup-info.json).'];
        }

        // Validate metadata format/version.
        $metaContent = $zip->getFromName('backup-info.json');
        $zip->close();

        if ($metaContent === false) {
            return ['valid' => false, 'error' => 'The backup metadata could not be read.'];
        }

        $meta = json_decode($metaContent, true);
        if (! is_array($meta)
            || ($meta['format'] ?? null) !== Config::get('backup.format', 'jc66-backup')
            || empty($meta['version'])) {
            return ['valid' => false, 'error' => 'The backup format or version is not recognized by this system.'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Extract a validated backup to a temp directory and return the path.
     */
    protected function extractToTemp(UploadedFile $file): string
    {
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'jc66_restore_'.now()->format('Ymd_His').'_'.uniqid();
        File::ensureDirectoryExists($tempDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            File::deleteDirectory($tempDir);
            throw new \RuntimeException('The archive could not be opened.');
        }

        $zip->extractTo($tempDir);
        $zip->close();

        return $tempDir;
    }

    /**
     * Restore the database from the extracted database.sql file.
     *
     * Uses the application's own PDO connection (pure-PHP restore), which works
     * in every environment. The CLI-based restorer remains available in
     * app/Services for environments where the native mysql client works.
     */
    protected function restoreDatabase(string $sqlFile): void
    {
        if (! file_exists($sqlFile)) {
            throw new \RuntimeException('The database dump was not found inside the backup.');
        }

        $this->restoreDatabaseViaPdo($sqlFile);
    }

    /**
     * Database restore using the mysql CLI client.
     */
    protected function restoreDatabaseViaCli(string $mysql, string $sqlFile): void
    {
        $db = $this->databaseConfig();

        // Wrap the dump so foreign-key / unique checks don't abort mid-import.
        $wrapped = tempnam(sys_get_temp_dir(), 'jc66_restore_sql_');
        file_put_contents($wrapped, "SET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n");
        file_put_contents($wrapped, fopen($sqlFile, 'r'), FILE_APPEND);
        file_put_contents($wrapped, "\nSET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n", FILE_APPEND);

        $command = [
            $mysql,
            '--host='.$db['host'],
            '--port='.$db['port'],
            '-u'.$db['username'],
        ];

        if ($db['password'] !== '' && $db['password'] !== null) {
            $command[] = '-p'.$db['password'];
        }

        $command[] = '--default-character-set=utf8mb4';
        $command[] = $db['database'];

        $result = Process::timeout(600)
            ->input(fopen($wrapped, 'r'))
            ->run($command);

        File::delete($wrapped);

        if (! $result->successful()) {
            throw new \RuntimeException('The database could not be restored: '.$result->errorOutput());
        }
    }

    /**
     * Database restore using the application's own PDO connection. Splits the
     * dump into statements (handling quotes, line/block comments and DELIMITER)
     * and executes them. Works anywhere the app can reach its database.
     */
    protected function restoreDatabaseViaPdo(string $sqlFile): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $content = file_get_contents($sqlFile);
        if ($content === false) {
            throw new \RuntimeException('The database dump could not be read.');
        }

        $statements = $this->splitSqlStatements($content);

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET UNIQUE_CHECKS=0');

        $errors = [];
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (Throwable $e) {
                // DROP/CREATE are idempotent and data is freshly inserted, so any
                // failure indicates a genuine problem with this backup.
                $errors[] = $e->getMessage().' :: '.mb_substr($statement, 0, 200);
            }
        }

        $pdo->exec('SET UNIQUE_CHECKS=1');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        if ($errors !== []) {
            throw new \RuntimeException('The database could not be fully restored: '.implode(' | ', array_slice($errors, 0, 3)));
        }
    }

    /**
     * Split a SQL dump into individual statements, honouring single/double
     * quoted strings (with backslash and doubled-quote escaping), line (#, --)
     * and block (/* *\/) comments, and DELIMITER changes.
     *
     * @return string[]
     */
    protected function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $delimiter = ';';
        $length = strlen($sql);
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $inLineComment = false;
        $inBlockComment = false;
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Whitespace / newline ends a line comment.
            if ($inLineComment) {
                $buffer .= $char;
                if ($char === "\n" || $char === "\r") {
                    $inLineComment = false;
                }
                $i++;
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i += 2;
                    continue;
                }
                $i++;
                continue;
            }

            // Inside a string: handle escape sequences.
            if ($inSingle || $inDouble) {
                if ($char === '\\' && $next !== '') {
                    $buffer .= $char.$next;
                    $i += 2;
                    continue;
                }
                if ($char === "'" && $inSingle) {
                    if ($next === "'") {
                        $buffer .= "''";
                        $i += 2;
                        continue;
                    }
                    $inSingle = false;
                    $buffer .= $char;
                    $i++;
                    continue;
                }
                if ($char === '"' && $inDouble) {
                    if ($next === '"') {
                        $buffer .= '""';
                        $i += 2;
                        continue;
                    }
                    $inDouble = false;
                    $buffer .= $char;
                    $i++;
                    continue;
                }
                $buffer .= $char;
                $i++;
                continue;
            }

            // Comment starts (outside strings).
            if ($char === '#' || ($char === '-' && $next === '-')) {
                $inLineComment = true;
                $buffer .= $char;
                $i++;
                continue;
            }
            if ($char === '/' && ($next === '*' || $next === '!')) {
                // Standard block comment (/* ... */) or MySQL conditional
                // executable comment (/*!12345 ... */) — both are skipped.
                $inBlockComment = true;
                $i += 2;
                continue;
            }

            // Quoted string starts.
            if ($char === "'") {
                $inSingle = true;
                $buffer .= $char;
                $i++;
                continue;
            }
            if ($char === '"') {
                $inDouble = true;
                $buffer .= $char;
                $i++;
                continue;
            }

            // Delimiter reached?
            if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $statement = trim($buffer);
                $buffer = '';

                if (preg_match('/^DELIMITER\s+(\S+)$/i', $statement, $m)) {
                    $delimiter = $m[1];
                } elseif ($statement !== '') {
                    $statements[] = $statement;
                }

                $i += strlen($delimiter);
                continue;
            }

            $buffer .= $char;
            $i++;
        }

        $tail = trim($buffer);
        if ($tail !== '' && ! preg_match('/^DELIMITER\b/i', $tail)) {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * Restore uploaded files from the extracted backup. Only files under the
     * approved application directories are written; path traversal is blocked.
     */
    protected function restoreFiles(string $tempDir): void
    {
        $map = Config::get('backup.restore_directories', [
            'storage' => 'app/public',
            'public' => '',
        ]);

        $allowedTops = array_keys($map);

        // Iterate extracted directory tree.
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                continue;
            }

            $relative = ltrim(substr($item->getPathname(), strlen($tempDir)), DIRECTORY_SEPARATOR);
            $relative = str_replace('\\', '/', $relative);

            $top = explode('/', $relative)[0] ?? '';
            if (! in_array($top, $allowedTops, true)) {
                // Unknown top-level folder — skip for safety.
                continue;
            }

            $rest = substr($relative, strlen($top) + 1);
            if ($rest === '') {
                continue;
            }

            if (str_contains($rest, '..') || str_starts_with($rest, '/')) {
                continue;
            }

            $mappedSub = $map[$top];
            if ($top === 'storage') {
                $targetBase = storage_path($mappedSub);
            } elseif ($top === 'public') {
                $targetBase = public_path($mappedSub);
            } else {
                continue;
            }

            $target = $targetBase.DIRECTORY_SEPARATOR.$rest;
            $target = str_replace('/', DIRECTORY_SEPARATOR, $target);

            // Final safety check: ensure the resolved target stays inside the base dir.
            $realBase = realpath($targetBase);
            $realTarget = realpath(dirname($target));
            if ($realBase === false || $realTarget === false || str_starts_with($realTarget, $realBase) === false) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target), 0755, true);
            File::copy($item->getPathname(), $target);
        }
    }

    /**
     * Full restore pipeline: validate -> safety backup -> restore db -> restore files.
     * Throws a RuntimeException with a user-safe message on failure.
     */
    public function restore(UploadedFile $file, User $user): void
    {
        $validation = $this->validateUploadedBackup($file);
        if (! $validation['valid']) {
            throw new \RuntimeException($validation['error'] ?? 'The backup file is invalid.');
        }

        $tempDir = $this->extractToTemp($file);

        try {
            // 1. Safety backup of the current system BEFORE touching anything.
            $this->createSafetyBackup($user);

            // 2. Restore database.
            $this->restoreDatabase($tempDir.DIRECTORY_SEPARATOR.'database.sql');

            // 3. Restore files.
            $this->restoreFiles($tempDir);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }
}
