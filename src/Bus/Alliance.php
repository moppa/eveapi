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
use Seat\Eveapi\Jobs\Alliances\Info;
use Seat\Eveapi\Jobs\Alliances\Members;
use Seat\Eveapi\Jobs\Contacts\Alliance\Contacts;
use Seat\Eveapi\Jobs\Contacts\Alliance\Labels;
use Seat\Eveapi\Models\RefreshToken;
use Throwable;

/**
 * Class Alliance.
 *
 * @package Seat\Eveapi\Bus
 */
class Alliance extends Bus
{
    /**
     * @var int
     */
    private int $alliance_id;

    /**
     * @var \Seat\Eveapi\Models\RefreshToken|null
     */
    private ?RefreshToken $token;

    /**
     * Alliance constructor.
     *
     * @param  int  $alliance_id
     * @param  \Seat\Eveapi\Models\RefreshToken|null  $token
     */
    public function __construct(int $alliance_id, ?RefreshToken $token = null)
    {
        parent::__construct();

        $this->token = $token;
        $this->alliance_id = $alliance_id;
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

        $alliance = \Seat\Eveapi\Models\Alliances\Alliance::firstOrNew(
            ['alliance_id' => $this->alliance_id],
            ['name' => "Unknown Alliance : {$this->alliance_id}"]
        );

        \Illuminate\Support\Facades\Bus::batch([$this->jobs->toArray()])
            ->then(function (Batch $batch) {
                logger()->debug(
                    sprintf('[Batches][%s] Alliance batch successfully completed.', $batch->id),
                    [
                        'id' => $batch->id,
                        'name' => $batch->name,
                    ]);
            })->catch(function (Batch $batch, Throwable $throwable) {
                logger()->error(
                    sprintf('[Batches][%s] An error occurred during Alliance batch processing.', $batch->id),
                    [
                        'id' => $batch->id,
                        'name' => $batch->name,
                        'error' => $throwable->getMessage(),
                        'trace' => $throwable->getTrace(),
                    ]);
            })->finally(function (Batch $batch) {
                logger()->info(
                    sprintf('[Batches][%s] Alliance batch executed.', $batch->id),
                    [
                        'id' => $batch->id,
                        'name' => $batch->name,
                        'stats' => [
                            'success' => $batch->totalJobs - $batch->failedJobs,
                            'failed' => $batch->failedJobs,
                            'total' => $batch->totalJobs,
                        ],
                    ]);
            })->onQueue('public')->name($alliance->name)->allowFailures()->dispatch();
    }

    /**
     * Seed jobs list with job which did not require authentication.
     *
     * @return void
     */
    protected function addPublicJobs()
    {
        $this->jobs->add(new Info($this->alliance_id));
        $this->jobs->add(new Members($this->alliance_id));
    }

    /**
     * Seed jobs list with job requiring authentication.
     *
     * @return void
     */
    protected function addAuthenticatedJobs()
    {
        $this->jobs->add(new Labels($this->alliance_id, $this->token));
        $this->jobs->add(new Contacts($this->alliance_id, $this->token));
    }
}
