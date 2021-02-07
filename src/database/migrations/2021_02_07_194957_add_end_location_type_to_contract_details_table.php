<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2020 Leon Jacobs
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seat\Eveapi\Models\Universe\UniverseStation;
use Seat\Eveapi\Models\Universe\UniverseStructure;

/**
 * Class AddEndLocationTypeToContractDetailsTable.
 */
class AddEndLocationTypeToContractDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contract_details', function (Blueprint $table) {
            $table->string('end_location_type')->nullable()->after('end_location_id');
        });

        // set stations
        DB::table('contract_details')
            ->whereBetween('end_location_id', [60000000, 60999999])
            ->orWhereBetween('end_location_id', [61000000, 63999999])
            ->orWhereBetween('end_location_id', [68000000, 68999999])
            ->orWhereBetween('end_location_id', [69000000, 69999999])
            ->update([
                'end_location_type' => UniverseStation::class,
            ]);

        // set structures
        DB::table('contract_details')
            ->whereNotNull('end_location_id')
            ->whereNotBetween('end_location_id', [60000000, 60999999])
            ->whereNotBetween('end_location_id', [61000000, 63999999])
            ->whereNotBetween('end_location_id', [68000000, 68999999])
            ->whereNotBetween('end_location_id', [69000000, 69999999])
            ->update([
                'end_location_type' => UniverseStructure::class,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contract_details', function (Blueprint $table) {
            $table->dropColumn('end_location_type');
        });
    }
}
