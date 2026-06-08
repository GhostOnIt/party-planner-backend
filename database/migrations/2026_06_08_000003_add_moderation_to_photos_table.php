<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('pending')->after('is_featured');
            $table->foreignUuid('moderated_by_user_id')->nullable()->after('moderation_status')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by_user_id');
            $table->text('moderation_reason')->nullable()->after('moderated_at');

            $table->index(['event_id', 'moderation_status']);
        });

        DB::table('photos')->update([
            'moderation_status' => 'approved',
            'moderated_at' => now(),
        ]);

        if (!DB::table('permissions')->where('name', 'photos.moderate')->exists()) {
            DB::table('permissions')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'photos.moderate',
                'display_name' => 'Moderer les photos',
                'description' => 'Permet de valider ou rejeter les photos avant publication',
                'module' => 'photos',
                'action' => 'moderate',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'photos.moderate')->delete();

        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'moderation_status']);
            $table->dropForeign(['moderated_by_user_id']);
            $table->dropColumn([
                'moderation_status',
                'moderated_by_user_id',
                'moderated_at',
                'moderation_reason',
            ]);
        });
    }
};
