<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eveapi\Bus;

use Illuminate\Bus\Batch;
use Seat\Eveapi\Jobs\Assets\Character\Assets;
use Seat\Eveapi\Jobs\Assets\Character\Locations;
use Seat\Eveapi\Jobs\Assets\Character\Names;
use Seat\Eveapi\Jobs\Calendar\Attendees;
use Seat\Eveapi\Jobs\Calendar\Detail;
use Seat\Eveapi\Jobs\Calendar\Events;
use Seat\Eveapi\Jobs\Character\Affiliation;
use Seat\Eveapi\Jobs\Character\AgentsResearch;
use Seat\Eveapi\Jobs\Character\Blueprints;
use Seat\Eveapi\Jobs\Character\CorporationHistory;
use Seat\Eveapi\Jobs\Character\Fatigue;
use Seat\Eveapi\Jobs\Character\Info;
use Seat\Eveapi\Jobs\Character\LoyaltyPoints;
use Seat\Eveapi\Jobs\Character\Medals;
use Seat\Eveapi\Jobs\Character\Roles;
use Seat\Eveapi\Jobs\Character\Standings;
use Seat\Eveapi\Jobs\Character\Titles;
use Seat\Eveapi\Jobs\Clones\Clones;
use Seat\Eveapi\Jobs\Clones\Implants;
use Seat\Eveapi\Jobs\Contacts\Character\Contacts;
use Seat\Eveapi\Jobs\Contacts\Character\Labels as ContactLabels;
use Seat\Eveapi\Jobs\Fittings\Character\Fittings;
use Seat\Eveapi\Jobs\Industry\Character\Jobs;
use Seat\Eveapi\Jobs\Industry\Character\Mining;
use Seat\Eveapi\Jobs\Location\Character\Location;
use Seat\Eveapi\Jobs\Location\Character\Online;
use Seat\Eveapi\Jobs\Location\Character\Ship;
use Seat\Eveapi\Jobs\Mail\Labels as MailLabels;
use Seat\Eveapi\Jobs\Mail\MailingLists;
use Seat\Eveapi\Jobs\Mail\Mails;
use Seat\Eveapi\Jobs\Market\Character\History;
use Seat\Eveapi\Jobs\Market\Character\Orders;
use Seat\Eveapi\Jobs\PlanetaryInteraction\Character\Planets;
use Seat\Eveapi\Jobs\Skills\Character\Attributes;
use Seat\Eveapi\Jobs\Skills\Character\Queue;
use Seat\Eveapi\Jobs\Skills\Character\Skills;
use Seat\Eveapi\Jobs\Wallet\Character\Balance;
use Seat\Eveapi\Jobs\Wallet\Character\Journal;
use Seat\Eveapi\Jobs\Wallet\Character\Transactions;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\RefreshToken;
use Throwable;

/**
 * Class Character.
 *
 * @package Seat\Eveapi\Bus
 */
class Character extends Bus
{
    /**
     * @var int
     */
    private int $character_id;

    /**
     * @var \Seat\Eveapi\Models\RefreshToken|null
     */
    private ?RefreshToken $token;

    /**
     * Character constructor.
     *
     * @param  int  $character_id
     * @param  \Seat\Eveapi\Models\RefreshToken|null  $token
     */
    public function __construct(int $character_id, ?RefreshToken $token = null)
    {
        parent::__construct();

        $this->character_id = $character_id;
        $this->token = $token;
    }

    /**
     * Dispatch jobs.
     *
     * @return void
     */
    public function fire()
    {
        $this->addPublicJobs();

        if (! is_null($this->token))
            $this->addAuthenticatedJobs();

        // Character
        $character = CharacterInfo::firstOrNew(
            ['character_id' => $this->character_id],
            ['name' => "Unknown Character : {$this->character_id}"]
        );

        \Illuminate\Support\Facades\Bus::batch([$this->jobs->toArray()])
            ->then(function (Batch $batch) {
                logger()->debug(
                    sprintf('[Batches][%s] Character batch successfully completed.', $batch->id),
                    [
                        'id' => $batch->id,
                        'name' => $batch->name,
                    ]);
            })->catch(function (Batch $batch, Throwable $throwable) {
                logger()->error(
                    sprintf('[Batches][%s] An error occurred during Character batch processing.', $batch->id),
                    [
                        'id' => $batch->id,
                        'name' => $batch->name,
                        'error' => $throwable->getMessage(),
                        'trace' => $throwable->getTrace(),
                    ]);
            })->finally(function (Batch $batch) {
                logger()->info(
                    sprintf('[Batches][%s] Character batch executed.', $batch->id),
                    [
                        'id' => $batch->id,
                        'name' => $batch->name,
                        'stats' => [
                            'success' => $batch->totalJobs - $batch->failedJobs,
                            'failed' => $batch->failedJobs,
                            'total' => $batch->totalJobs,
                        ],
                    ]);
            })->onQueue('characters')->name($character->name)->allowFailures()->dispatch();
    }

    /**
     * Seed jobs list with job which did not require authentication.
     *
     * @return void
     */
    protected function addPublicJobs()
    {
        $this->jobs->add(new Info($this->character_id));
        $this->jobs->add(new CorporationHistory($this->character_id));
        $this->jobs->add(new Affiliation([$this->character_id]));
    }

    /**
     * Seed jobs list with job requiring authentication.
     *
     * @return void
     */
    protected function addAuthenticatedJobs()
    {
        $this->jobs->add(new Roles($this->token));
        $this->jobs->add(new Titles($this->token));
        $this->jobs->add(new Clones($this->token));
        $this->jobs->add(new Implants($this->token));

        $this->jobs->add(new Location($this->token));
        $this->jobs->add(new Online($this->token));
        $this->jobs->add(new Ship($this->token));

        $this->jobs->add(new Attributes($this->token));
        $this->jobs->add(new Queue($this->token));
        $this->jobs->add(new Skills($this->token));

        // collect military information
        $this->jobs->add(new Fittings($this->token));

        $this->jobs->add(new Fatigue($this->token));
        $this->jobs->add(new Medals($this->token));

        // collect industrial information
        $this->jobs->add(new Blueprints($this->token));
        $this->jobs->add(new Jobs($this->token));
        $this->jobs->add(new Mining($this->token));
        $this->jobs->add(new AgentsResearch($this->token));

        // collect financial information
        $this->jobs->add(new Orders($this->token));
        $this->jobs->add(new History($this->token));
        $this->jobs->add(new Planets($this->token));
        $this->jobs->add(new Balance($this->token));
        $this->jobs->add(new Journal($this->token));
        $this->jobs->add(new Transactions($this->token));
        $this->jobs->add(new LoyaltyPoints($this->token));

        // collect intel information
        $this->jobs->add(new Standings($this->token));
        $this->jobs->add(new Contacts($this->token));
        $this->jobs->add(new ContactLabels($this->token));

        $this->jobs->add(new MailLabels($this->token));
        $this->jobs->add(new MailingLists($this->token));
        $this->jobs->add(new Mails($this->token));

        // calendar events
        $this->jobs->add(new Events($this->token));
        $this->jobs->add(new Detail($this->token));
        $this->jobs->add(new Attendees($this->token));

        // assets
        $this->jobs->add(new Assets($this->token));
        $this->jobs->add(new Names($this->token));
        $this->jobs->add(new Locations($this->token));
    }
}
