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
        Schema::table('profiles', function (Blueprint $table) {
            $table->enum('account_status', ['PENDING_VALIDATION', 'VALIDATED', 'REJECTED', 'DISABLED'])
                ->default('PENDING_VALIDATION')
                ->change();

            // Keeps the status the profile had before being disabled,
            // so it can be restored when the owner enables it again.
            $table->string('account_status_before_disabled')->nullable()->after('account_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('profiles')
            ->where('account_status', 'DISABLED')
            ->update([
                'account_status' => DB::raw("COALESCE(account_status_before_disabled, 'PENDING_VALIDATION')"),
            ]);

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('account_status_before_disabled');

            $table->enum('account_status', ['PENDING_VALIDATION', 'VALIDATED', 'REJECTED'])
                ->default('PENDING_VALIDATION')
                ->change();
        });
    }
};
