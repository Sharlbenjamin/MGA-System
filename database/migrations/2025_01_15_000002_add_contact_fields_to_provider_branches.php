<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add contact fields
        Schema::table('provider_branches', function (Blueprint $table) {
            $table->string('email')->nullable()->after('branch_name');
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
        });

        // Step 2: Copy contact data with priority logic
        // Priority: Operation Contact > GOP Contact > Financial Contact
        
        // First, copy from Operation Contact (highest priority)
        DB::statement("
            UPDATE provider_branches pb
            INNER JOIN contacts c ON pb.operation_contact_id = c.id
            SET 
                pb.email = CASE 
                    WHEN c.preferred_contact = 'Email' THEN c.email
                    WHEN c.preferred_contact = 'Second Email' THEN c.second_email
                    ELSE COALESCE(c.email, c.second_email)
                END,
                pb.phone = CASE 
                    WHEN c.preferred_contact = 'Phone' THEN c.phone_number
                    WHEN c.preferred_contact = 'Second Phone' THEN c.second_phone
                    ELSE COALESCE(c.phone_number, c.second_phone)
                END,
                pb.address = c.address
            WHERE pb.operation_contact_id IS NOT NULL 
            AND c.id IS NOT NULL
        ");

        // Second, copy from GOP Contact (if no Operation Contact or if Operation Contact doesn't have data)
        DB::statement("
            UPDATE provider_branches pb
            INNER JOIN contacts c ON pb.gop_contact_id = c.id
            SET 
                pb.email = CASE 
                    WHEN pb.email IS NULL OR pb.email = '' THEN
                        CASE 
                            WHEN c.preferred_contact = 'Email' THEN c.email
                            WHEN c.preferred_contact = 'Second Email' THEN c.second_email
                            ELSE COALESCE(c.email, c.second_email)
                        END
                    ELSE pb.email
                END,
                pb.phone = CASE 
                    WHEN pb.phone IS NULL OR pb.phone = '' THEN
                        CASE 
                            WHEN c.preferred_contact = 'Phone' THEN c.phone_number
                            WHEN c.preferred_contact = 'Second Phone' THEN c.second_phone
                            ELSE COALESCE(c.phone_number, c.second_phone)
                        END
                    ELSE pb.phone
                END,
                pb.address = CASE 
                    WHEN pb.address IS NULL OR pb.address = '' THEN c.address
                    ELSE pb.address
                END
            WHERE pb.gop_contact_id IS NOT NULL 
            AND c.id IS NOT NULL
        ");

        // Third, copy from Financial Contact (if no Operation or GOP Contact, or if they don't have data)
        DB::statement("
            UPDATE provider_branches pb
            INNER JOIN contacts c ON pb.financial_contact_id = c.id
            SET 
                pb.email = CASE 
                    WHEN pb.email IS NULL OR pb.email = '' THEN
                        CASE 
                            WHEN c.preferred_contact = 'Email' THEN c.email
                            WHEN c.preferred_contact = 'Second Email' THEN c.second_email
                            ELSE COALESCE(c.email, c.second_email)
                        END
                    ELSE pb.email
                END,
                pb.phone = CASE 
                    WHEN pb.phone IS NULL OR pb.phone = '' THEN
                        CASE 
                            WHEN c.preferred_contact = 'Phone' THEN c.phone_number
                            WHEN c.preferred_contact = 'Second Phone' THEN c.second_phone
                            ELSE COALESCE(c.phone_number, c.second_phone)
                        END
                    ELSE pb.phone
                END,
                pb.address = CASE 
                    WHEN pb.address IS NULL OR pb.address = '' THEN c.address
                    ELSE pb.address
                END
            WHERE pb.financial_contact_id IS NOT NULL 
            AND c.id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'address']);
        });
    }
};
