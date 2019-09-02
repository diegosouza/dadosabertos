<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use NcJoes\PopplerPhp\Config;
use NcJoes\PopplerPhp\PdfToText;

class ExtractCommand extends Command
{
    protected $signature = 'extract {filename : PDF a ser carregado, para todos, utilize all }';

    protected $description = 'Extrai conteúdo de PDF';

    public function handle()
    {
        Config::setBinDirectory('/usr/bin/');

        $filename = $this->argument('filename');

        if ($filename == 'all') {

            $allFiles = collect(Storage::allFiles());

            $pdfFilenames = $allFiles->filter(function($filename) {
                return Storage::mimeType($filename) == 'application/pdf';
            });

            $pdfFilenames->each(function($pdfFilename){
                (new PdfToText('storage/' . $pdfFilename))->generate();
            });

        } else {
            (new PdfToText('storage/' . $filename))->generate();
        }
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
