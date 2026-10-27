<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // Integer tokenable_ids cannot map to UUID users; revoke those sessions first.
            DB::table('personal_access_tokens')->delete();

            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropMorphs('tokenable');
            });

            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->uuidMorphs('tokenable');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('personal_access_tokens')->delete();

            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropMorphs('tokenable');
            });

            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->morphs('tokenable');
            });
        });
    }
};
