<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('current_owner_id')->nullable()->after('remember_token');
            $table->foreign('current_owner_id')->references('id')->on('owners')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_owner_id']);
            $table->dropColumn('current_owner_id');
        });
    }
};
