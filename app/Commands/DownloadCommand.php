<?php

namespace App\Commands;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleRetry\GuzzleRetryMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use function \GuzzleHttp\Psr7\parse_header;

class DownloadCommand extends Command
{
    protected $signature = "download:diario-oficial
                            {from : data no formato dd/mm/YYYY}
                            {to   : data no formato dd/mm/YYYY}";

    protected $description = 'Baixa arquivos PDFs do diário oficial de Santos';

    public function handle()
    {
        $dateFormat = "d/m/Y";
        $defaultFormat = "Y-m-d";

        $fromOption = $this->argument('from');
        $toOption = $this->argument('to');

        $from = Carbon::createFromFormat($dateFormat, $fromOption);
        $to = Carbon::createFromFormat($dateFormat, $toOption);

        $fromFormatted = $from->format($defaultFormat);
        $toFormatted = $to->format($defaultFormat); 

        $dates = CarbonImmutable::create($fromFormatted)
            ->daysUntil($toFormatted)
            ->toArray();

        $uris = $this->getUris($dates);

        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory());
        
        $client = new Client([
            'retry_on_timeout'   => true,
            'connect_timeout'    => 10,
            'timeout'            => 20,
            'max_retry_attempts' => 3,
            'handler'            => $stack,
        ]);
        
        $requests = function($uris) {
            foreach ($uris as $uri) {
                yield new Request('GET', $uri);
            }
        };

        $pool = new Pool($client, $requests($uris), [
            'concurrency' => 5,

            'fulfilled'   => function ($response) {
                if ($response->getStatusCode() === 200 && $response->hasHeader('Content-Disposition')) {
                    $header = $response->getHeader('Content-Disposition');
                    $filename = parse_header($header)[0]['filename'];

                    Storage::put($filename, $response->getBody());
                    
                    $this->line("{$filename} was downloaded and saved!");                    
                } else {
                    $this->warn($response->getBody());
                }

            },
            'rejected' => function ($reason) {
                $request = $reason->getRequest();
                $response = $reason->getResponse();
                $this->info("{$request->getUri()} failed!");
            }
        ]);

        $begin = CarbonImmutable::now();
        $this->info("######## BEGIN: " . $begin);

        $pool->promise()->wait();

        $end = CarbonImmutable::now();
        $this->info("########## END: " . $end);
        $this->info("# TIME ELAPSED: " . $begin->diffInSeconds($end) . " seconds");
    }

    private function getUris($dates)
    {
        return array_map(function($date) {
            return "https://diariooficial.santos.sp.gov.br/edicoes/inicio/download/{$date->format('Y-m-d')}";
        }, $dates);
    }

    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
