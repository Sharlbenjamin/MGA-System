<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_push_tokens')) {
            return;
        }

        Schema::table('mobile_push_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_push_tokens', 'notification_sound')) {
                $table->string('notification_sound', 40)->default('default')->after('platform');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mobile_push_tokens')) {
            return;
        }

        Schema::table('mobile_push_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('mobile_push_tokens', 'notification_sound')) {
                $table->dropColumn('notification_sound');
            }
        });
    }
};
