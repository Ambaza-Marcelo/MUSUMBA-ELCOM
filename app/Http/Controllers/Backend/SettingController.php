<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use \App\Models\Setting;

class SettingController extends Controller
{
    //
    public function index()
    {
        $settings = Setting::orderBy('created_at', 'desc')->get();
        return view('backend.pages.setting.index', compact('settings'));
    }
    public function create()
    {
    	return view('backend.pages.setting.create');
    }

     public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required',
            'nif' => 'required',
            'rc' => 'required',
            'commune' => 'required',
            'province' => 'required',
            'zone' => 'required',

            'tp_type' => 'required',
            'vat_taxpayer' => 'required',
            'ct_taxpayer' => 'required',
            'tl_taxpayer' => 'required',
            'tp_fiscal_center' => 'required',
            'tp_activity_sector' => 'required',
            'tp_legal_form' => 'required',
            'postal_number' => 'required',
            'quartier' => 'required',
            'rue' => 'required',
            'max_line' => 'required',
            'logo' => 'required|mimes:jpeg,jpg,png,svg|max:2048',

        ]);

        

        $storagepath = $request->file('logo')->store('public/logo');
        $fileName = basename($storagepath);

        $data = $request->except(['bank_name', 'account_number']);
        $data['logo'] = $fileName;
        $data['bank_name'] = $this->sanitizeBankArrays($request->bank_name ?? []);
        $data['account_number'] = $this->sanitizeBankArrays($request->account_number ?? []);

        Setting::create($data);

        return redirect()->route('admin.settings.index')->with('success', __('messages.setting_created'));
    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $setting = Setting::findOrFail($id);
        return view('backend.pages.setting.edit', compact('setting'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        $request->validate([
            'name' => 'required',
            'nif' => 'required',
            'rc' => 'required',
            'commune' => 'required',
            'province' => 'required',
            'zone' => 'required',

            'tp_type' => 'required',
            'vat_taxpayer' => 'required',
            'ct_taxpayer' => 'required',
            'tl_taxpayer' => 'required',
            'tp_fiscal_center' => 'required',
            'tp_activity_sector' => 'required',
            'tp_legal_form' => 'required',
            'postal_number' => 'required',
            'quartier' => 'required',
            'rue' => 'required',
            'max_line' => 'required',
            'logo' => 'required|mimes:jpeg,jpg,png,svg|max:2048',

        ]);

        $setting = Setting::findOrFail($id);

        $data = $request->except(['bank_name', 'account_number']);
        $data['bank_name'] = $this->sanitizeBankArrays($request->bank_name ?? []);
        $data['account_number'] = $this->sanitizeBankArrays($request->account_number ?? []);

        if($request->hasFile('logo')){
            $file_path = "public/logo/".$setting->logo;
            Storage::delete($file_path);

            $storagepath = $request->file('logo')->store('public/logo');
            $fileName = basename($storagepath);
            $data['logo'] = $fileName;

        }

        $setting->fill($data);
        $setting->save();

        return redirect()->route('admin.settings.index');
    }

    public function postDbBackUp () {
    $now =  Carbon::now()->format("Y-m-d-H-m-i").'-backup.sql';
    try {
        Artisan::call('db:mssms',
            [
                '--database' => 'mysql',
                '--destination' => 'G:\Musumba',
                '--destinationPath' =>$now,
                '--compression' => 'gzip'
            ]
        );
    }
    catch(\Exception $e) {
        return Response::json([
            'success' => false,
            'errors' => ""
        ], 400);
    }
    return Response::json([
        'success' => true,
        'message' => 'success'
    ]);

}

    /**
     * Sanitize bank_name and account_number arrays (remove empty entries, ensure arrays).
     *
     * @param mixed $value
     * @return array
     */
    private function sanitizeBankArrays($value)
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', $value), function ($v) {
            return $v !== '' && $v !== null;
        }));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $setting = Setting::findOrFail($id);
        $file_path = "public/logo/".$setting->logo;
            Storage::delete($file_path);
        $setting->delete();
        return redirect()->back();
    }


    public function documentation(){
        return view('backend.pages.documentation');
    }

    /**
     * Sauvegarde de la base téléchargée en .sql ou compressée en .zip (format=zip).
     *
     * @return \Illuminate\Http\Response
     */
    public function backupDatabase(Request $request)
    {
        @set_time_limit(0);

        $database = Config::get('database.connections.mysql.database');
        $baseName = 'backup_' . $database . '_' . date('Y-m-d_H-i-s');
        $directory = storage_path('app/backups');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $sqlPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.sql';

        try {
            if (!$this->dumpWithMysqldump($sqlPath)) {
                $this->dumpWithPdo($sqlPath);
            }

            if ($request->input('format') !== 'zip') {
                return response()->download($sqlPath, $baseName . '.sql', ['Content-Type' => 'application/sql'])
                    ->deleteFileAfterSend(true);
            }

            if (!class_exists(\ZipArchive::class)) {
                @unlink($sqlPath);
                return redirect()->back()->with('error', 'L\'extension PHP zip n\'est pas activée sur le serveur : export ZIP impossible.');
            }

            $zipPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                @unlink($sqlPath);
                return redirect()->back()->with('error', 'Impossible de créer l\'archive ZIP de la sauvegarde.');
            }
            $zip->addFile($sqlPath, $baseName . '.sql');
            $zip->close();
            @unlink($sqlPath);

            return response()->download($zipPath, $baseName . '.zip', ['Content-Type' => 'application/zip'])
                ->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            @unlink($sqlPath);
            return redirect()->back()->with('error', __('messages.save_error') . ' ' . $e->getMessage());
        }
    }

    /**
     * Écrit la sauvegarde dans $path avec mysqldump ; false si l'outil est introuvable ou échoue.
     */
    private function dumpWithMysqldump(string $path): bool
    {
        $mysqldumpPath = $this->findMysqldump();
        if ($mysqldumpPath === null) {
            return false;
        }

        $command = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers --result-file=%s %s',
            escapeshellarg($mysqldumpPath),
            escapeshellarg(Config::get('database.connections.mysql.host')),
            escapeshellarg(Config::get('database.connections.mysql.port', '3306')),
            escapeshellarg(Config::get('database.connections.mysql.username')),
            escapeshellarg((string) Config::get('database.connections.mysql.password')),
            escapeshellarg($path),
            escapeshellarg(Config::get('database.connections.mysql.database'))
        );

        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0 || !is_file($path) || filesize($path) === 0) {
            @unlink($path);
            return false;
        }

        return true;
    }

    private function findMysqldump(): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return 'mysqldump';
        }

        $possiblePaths = [];
        $laragonBase = 'C:\\laragon\\bin\\mysql';
        if (is_dir($laragonBase)) {
            foreach (glob($laragonBase . '\\mysql-*', GLOB_ONLYDIR) as $mysqlDir) {
                $possiblePaths[] = $mysqlDir . '\\bin\\mysqldump.exe';
            }
        }
        $possiblePaths[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        $possiblePaths[] = 'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe';

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        $testOutput = [];
        $testReturn = 1;
        exec('where mysqldump.exe 2>nul', $testOutput, $testReturn);

        return ($testReturn === 0 && !empty($testOutput)) ? 'mysqldump.exe' : null;
    }

    /**
     * Sauvegarde de secours via PDO, écrite table par table dans $path.
     */
    private function dumpWithPdo(string $path): void
    {
        $database = Config::get('database.connections.mysql.database');
        $pdo = DB::connection('mysql')->getPdo();

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'écrire le fichier de sauvegarde.');
        }

        try {
            fwrite($handle, "-- Database Backup\n");
            fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- Database: {$database}\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                fwrite($handle, "-- Table structure for table `{$table}`\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                fwrite($handle, $createTable['Create Table'] . ";\n\n");

                $statement = $pdo->query("SELECT * FROM `{$table}`");
                $columns = null;
                $batch = [];
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    if ($columns === null) {
                        $columns = '`' . implode('`, `', array_keys($row)) . '`';
                        fwrite($handle, "-- Dumping data for table `{$table}`\n");
                    }
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = $value === null ? 'NULL' : $pdo->quote($value);
                    }
                    $batch[] = '(' . implode(', ', $values) . ')';

                    if (count($batch) >= 500) {
                        fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }
}
