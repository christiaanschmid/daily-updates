<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The timezone "today" follows for this user. Null uses the app's default,
            // or the Mac's own timezone in the desktop app.
            $table->string('timezone', 64)->nullable()->after('email');

            // Full history on the web, given by hand until subscriptions exist.
            $table->boolean('cloud_plan_granted')->default(false)->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'cloud_plan_granted']);
        });
    }
};
