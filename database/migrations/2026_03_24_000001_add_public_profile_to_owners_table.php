<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->text('about')->nullable()->after('tagline');
            $table->string('logo_url')->nullable()->after('about');
            $table->string('cover_url')->nullable()->after('logo_url');
            $table->string('website')->nullable()->after('cover_url');
            $table->string('email')->nullable()->after('website');
            $table->string('phone', 30)->nullable()->after('email');
            $table->unsignedSmallInteger('founded')->nullable()->after('phone');
            $table->boolean('featured')->default(false)->after('founded');
            $table->decimal('headquarters_lat', 10, 7)->nullable()->after('featured');
            $table->decimal('headquarters_lng', 10, 7)->nullable()->after('headquarters_lat');

            $table->index('featured');
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
