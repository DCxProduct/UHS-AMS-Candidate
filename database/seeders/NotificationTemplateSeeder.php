<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Application Approved',
                'code' => 'STAGE_APPROVED',
                'type' => 'announcement',
                'subject_en' => 'Your application has been approved',
                'subject_km' => 'ពាក្យស្នើសុំរបស់អ្នកត្រូវបានអនុម័ត',
                'body_en' => "Dear {candidate_name},\n\nYour application for {stage_name} has been reviewed and approved.\n\n{notes}",
                'body_km' => "សូមជម្រាបជូន {candidate_name}!\n\nពាក្យស្នើសុំរបស់អ្នកសម្រាប់ {stage_name} ត្រូវបានពិនិត្យ និងអនុម័តរួចរាល់ហើយ។\n\n{notes}",
                'channels' => ['database', 'mail'],
                'is_active' => true,
            ],
            [
                'name' => 'Application Rejected',
                'code' => 'STAGE_REJECTED',
                'type' => 'announcement',
                'subject_en' => 'Your application has been rejected',
                'subject_km' => 'ពាក្យស្នើសុំរបស់អ្នកត្រូវបានបដិសេធ',
                'body_en' => "Dear {candidate_name},\n\nWe regret to inform you that your application for {stage_name} was not accepted.\n\nReason: {notes}",
                'body_km' => "សូមជម្រាបជូន {candidate_name}!\n\nយើងខ្ញុំសូមជម្រាបថាពាក្យស្នើសុំរបស់អ្នកសម្រាប់ {stage_name} មិនត្រូវបានទទួលយកទេ។\n\nមូលហេតុ៖ {notes}",
                'channels' => ['database', 'mail'],
                'is_active' => true,
            ],
            [
                'name' => 'Payment Required',
                'code' => 'STAGE_PAYMENT_REQUIRED',
                'type' => 'request',
                'subject_en' => 'Payment required for your application',
                'subject_km' => 'តម្រូវឱ្យធ្វើការបង់ប្រាក់សម្រាប់ពាក្យស្នើសុំ',
                'body_en' => "Dear {candidate_name},\n\nYour application for {stage_name} requires payment. Please submit your payment slip to complete your application.\n\n{notes}",
                'body_km' => "សូមជម្រាបជូន {candidate_name}!\n\nពាក្យស្នើសុំរបស់អ្នកសម្រាប់ {stage_name} តម្រូវឱ្យធ្វើការបង់ប្រាក់។ សូមភ្ជាប់បង្កាន់ដៃបង់ប្រាក់ដើម្បីបន្តដំណើរការ។\n\n{notes}",
                'channels' => ['database', 'mail'],
                'is_active' => true,
            ],
            [
                'name' => 'New Candidate Submission',
                'code' => 'NEW_CANDIDATE_SUBMISSION',
                'type' => 'announcement',
                'subject_en' => 'New candidate application submitted',
                'subject_km' => 'មានការដាក់ពាក្យស្នើសុំថ្មីពីបេក្ខជន',
                'body_en' => "A new candidate submission has been received from {candidate_name} for form {stage_name}.\n\nPlease review the application details in the management portal.",
                'body_km' => "ទទួលបានការដាក់ពាក្យស្នើសុំថ្មីពីបេក្ខជន {candidate_name} សម្រាប់ទម្រង់ {stage_name}។\n\nសូមចូលទៅពិនិត្យមើលព័ត៌មានលម្អិតក្នុងប្រព័ន្ធគ្រប់គ្រង។",
                'channels' => ['database', 'mail'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::query()->updateOrCreate(
                ['code' => $template['code']],
                $template
            );
        }
    }
}
