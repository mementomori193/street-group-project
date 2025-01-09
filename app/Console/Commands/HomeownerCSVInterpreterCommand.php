<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HomeownerCSVInterpreterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'homeowners:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';


    // ideally, i'd use some kind of plugin or something to
    // define all the potential titles, but this will do for now
    protected $titles = [
        'Mr',
        'Ms',
        'Mrs',
        'Dr',
        'Prof',
        'Mister'
    ];


    protected $connectors = [
        'and',
        '&'
    ];


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csv = str_getcsv(Storage::disk('local')->get('homeowners.csv'));

        $names = [];

        $count = 1;
        foreach ($csv as $line) {
            // make sure to skip over title row
            if ($count == 1) {
                $count++;
                continue;
            }

            $nameArray = collect(explode(' ',  trim($line)));
            $connectors = array_intersect($this->connectors, $nameArray->toArray());

            if(count($connectors) >= 1) {
                $sharedLastName = $nameArray->last();
                $connectors = array_intersect($this->connectors, $nameArray->toArray());
                $connectorIndex = array_search(collect($connectors)->first(), $nameArray->toArray());


                $people[] = $nameArray->slice(0, $connectorIndex);
                $people[] = $nameArray->splice($connectorIndex + 1);


                foreach($people as $person) {
                    $names[] = $this->parseRow($person, $sharedLastName);
                }
                continue;
            }
            $names[] = $this->parseRow($nameArray);
        };

        print_r($names);
        return Command::SUCCESS;
    }

    /**
     * @param Collection $person
     * @return array
     */
    public function parseRow(\Illuminate\Support\Collection $person, string $sharedSurname = null): array
    {
        $name = [];


        if($sharedSurname) {
            $name['last_name'] = $sharedSurname;
        };

        foreach ($person as $value) {
            // set the title
            if (in_array($value, $this->titles)) {
                $name['title'] = $value;
                continue;
            }

            // check if a surname has been set, and there is more than a single entry other than the title
            if (($person->count() - 1) > 1) {
                // this means that there is more than one name.
                // now we need to decide whether or not we're dealing with a name or an initial
                $isInitial = strlen($value) == 2 || str_contains($value, '.');

                if ($isInitial) {
                    $name['initial'] = $value;
                } else {
                    if (!isset($name['first_name'])) {
                        $name['first_name'] = $value;
                    } else {
                        $name['last_name'] = $value;
                    }
                }
            } else {
                $name['last_name'] = $value;
            }
        }

        return $name;
    }
}
