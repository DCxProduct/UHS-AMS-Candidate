<?php

namespace App\Models;

use App\Models\Concerns\HasTemplateText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-editable system emails, in English and Khmer.
 *
 * The body is designed in the rich editor and may use {{ variables }}
 * (see VARIABLES). Empty fields fall back to the defaults below, so emails
 * keep working before anything is edited.
 */
class EmailTemplate extends Model
{
    use HasTemplateText;

    public const RESET_PASSWORD = 'reset_password';

    public const TEMPLATES = [self::RESET_PASSWORD];

    /** Variables offered by "Insert Variable" for each email. */
    public const VARIABLES = [
        self::RESET_PASSWORD => ['name', 'email', 'minutes', 'app', 'reset_url', 'reset_button'],
    ];

    protected $fillable = [
        'key',
        'header_title',
        'logo_path',
        'custom_variables',
        'subject_en', 'subject_km',
        'button_en', 'button_km',
        'body_en', 'body_km',
    ];

    /**
     * Built-in texts. They match the email that was sent before templates existed.
     */
    public static function defaults(string $key): array
    {
        return match ($key) {
            self::RESET_PASSWORD => [
                'header_title' => 'UHS-AMS',
                'subject_en' => 'Reset your password',
                'subject_km' => 'កំណត់ពាក្យសម្ងាត់ថ្មី',
                'button_en' => 'Reset Password',
                'button_km' => 'កំណត់ពាក្យសម្ងាត់ថ្មី',
                'body_en' => '<h2>Hello!</h2>'
                    .'<p>You are receiving this email because we received a password reset request for your account.</p>'
                    .'<p style="text-align: center;">{{ reset_button }}</p>'
                    .'<p>This password reset link will expire in {{ minutes }} minutes.</p>'
                    .'<p>If you did not request a password reset, no further action is required.</p>'
                    .'<p>Regards,<br>{{ app }}</p>',
                'body_km' => '<h2>សួស្តី!</h2>'
                    .'<p>អ្នកទទួលបានអ៊ីមែលនេះ ដោយសារយើងបានទទួលសំណើកំណត់ពាក្យសម្ងាត់ថ្មីសម្រាប់គណនីរបស់អ្នក។</p>'
                    .'<p style="text-align: center;">{{ reset_button }}</p>'
                    .'<p>តំណកំណត់ពាក្យសម្ងាត់ថ្មីនេះនឹងផុតកំណត់ក្នុងរយៈពេល {{ minutes }} នាទី។</p>'
                    .'<p>ប្រសិនបើអ្នកមិនបានស្នើកំណត់ពាក្យសម្ងាត់ថ្មីទេ អ្នកមិនចាំបាច់ធ្វើអ្វីទេ។</p>'
                    .'<p>ដោយក្តីគោរព,<br>{{ app }}</p>',
            ],
            default => [],
        };
    }

    public function label(): string
    {
        return __('email_templates.templates.'.$this->key);
    }

    public function headerTitle(): string
    {
        return filled($this->header_title)
            ? (string) $this->header_title
            : (string) (self::defaults($this->key)['header_title'] ?? config('app.name'));
    }

    /**
     * Full path of the uploaded logo, when it exists on the public disk.
     */
    public function logoFullPath(): ?string
    {
        if (blank($this->logo_path) || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->path($this->logo_path);
    }

    /**
     * The designed body as HTML with {{ variables }} filled in. Values are
     * escaped, except those in $html (for example the button).
     */
    public function body(string $locale, array $variables = [], array $html = []): string
    {
        $values = array_map(fn ($value): string => e((string) $value), $variables);

        return self::fillVariables($this->raw('body', $locale), [...$values, ...$html]);
    }
}
