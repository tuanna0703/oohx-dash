<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POI snapshots — persist OSM/Google/Foursquare POI per (lat, lon, radius, source).
 *
 * Replaces volatile Laravel cache (lost on cache:clear) with durable storage.
 * Designed to support future POI microservice writing scoring/features columns.
 *
 * Key design:
 *  - lat_key/lon_key as VARCHAR (5 decimals = ~1m precision) for exact-match unique
 *  - source column: 'osm' now, 'google_places' / 'foursquare' / etc. later
 *  - features: precomputed aggregation (categories, distances) for fast read
 *  - scoring:  microservice output (foot_traffic_score, demographic_score...)
 *  - expires_at: per-source TTL (OSM: 30d, real-time mobility: 1d)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('poi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('lat_key', 20);
            $table->string('lon_key', 20);
            $table->unsignedSmallInteger('radius')->default(500);
            $table->string('source', 32)->default('osm');
            $table->json('pois');
            $table->unsignedInteger('poi_count')->default(0);
            $table->json('features')->nullable();
            $table->json('scoring')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['lat_key', 'lon_key', 'radius', 'source'], 'uniq_poi_loc_source');
            $table->index('fetched_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poi_snapshots');
    }
};
