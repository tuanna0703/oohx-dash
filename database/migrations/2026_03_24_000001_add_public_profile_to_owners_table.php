<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $cols = Schema::getColumnListing('owners');
            if (! in_array('tagline', $cols))         $table->string('tagline')->nullable()->after('name');
            if (! in_array('about', $cols))            $table->text('about')->nullable()->after('tagline');
            if (! in_array('logo_url', $cols))         $table->string('logo_url')->nullable()->after('about');
            if (! in_array('cover_url', $cols))        $table->string('cover_url')->nullable()->after('logo_url');
            if (! in_array('website', $cols))          $table->string('website')->nullable()->after('cover_url');
            if (! in_array('email', $cols))            $table->string('email')->nullable()->after('website');
            if (! in_array('phone', $cols))            $table->string('phone', 30)->nullable()->after('email');
            if (! in_array('founded', $cols))          $table->unsignedSmallInteger('founded')->nullable()->after('phone');
            if (! in_array('featured', $cols))         $table->boolean('featured')->default(false)->after('founded');
            if (! in_array('headquarters_lat', $cols)) $table->decimal('headquarters_lat', 10, 7)->nullable()->after('featured');
            if (! in_array('headquarters_lng', $cols)) $table->decimal('headquarters_lng', 10, 7)->nullable()->after('headquarters_lat');
        });

        $ownerIndexes = collect(\DB::select("SHOW INDEX FROM owners"))->pluck('Key_name');
        Schema::table('owners', function (Blueprint $table) use ($ownerIndexes) {
            if (! $ownerIndexes->contains('owners_featured_index')) {
                $table->index('featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropIndex(['featured']);
            $table->dropColumn([
                'tagline', 'about', 'logo_url', 'cover_url',
                'website', 'email', 'phone', 'founded',
                'featured', 'headquarters_lat', 'headquarters_lng',
            ]);
        });
    }
};
