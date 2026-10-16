<?php

namespace App\Models;

use App\Models\Concerns\HasTemplateText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-editable system emails. Admins write the text in Khmer or English
 * and every recipient gets it as written.
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

    /** Variables offered by templates an admin creates (form, stage, message and status come from a workflow stage). */
    public const GENERAL_VARIABLES = ['name', 'email', 'app', 'form', 'stage', 'message', 'status'];

    public const LANG_FILE = 'email_templates';

    protected $fillable = [
        'key',
        'name',
        'action',
        'header_title',
        'logo_path',
        'custom_variables',
        'subject',
        'button',
        'body',
    ];

    /** The UHS logo, used by the built-in emails until another logo is uploaded. */
    public const DEFAULT_LOGO = 'images/UHS_logo.png';

    /**
     * Built-in texts for UHS, in Khmer and English.
     */
    public static function defaults(string $key): array
    {
        return match ($key) {
            self::RESET_PASSWORD => [
                'header_title' => 'University of Health Sciences',
                'subject' => 'កំណត់ពាក្យសម្ងាត់ថ្មី | Reset your password',
                'button' => 'កំណត់ពាក្យសម្ងាត់ថ្មី | Reset Password',
                'body' => '<h2>សួស្តី {{ name }}!</h2>'
                    .'<p>យើងបានទទួលសំណើកំណត់ពាក្យសម្ងាត់ថ្មី សម្រាប់គណនីរបស់អ្នកនៅលើប្រព័ន្ធចុះឈ្មោះបេក្ខជន នៃសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល។</p>'
                    .'<p>We received a request to reset the password for your account on the University of Health Sciences candidate system.</p>'
                    .'<p style="text-align: center;">{{ reset_button }}</p>'
                    .'<p>តំណនេះមានសុពលភាពរយៈពេល {{ minutes }} នាទី។<br>This link expires in {{ minutes }} minutes.</p>'
                    .'<p>ប្រសិនបើអ្នកមិនបានស្នើសុំទេ សូមកុំអើពើនឹងអ៊ីមែលនេះ។<br>If you did not request a password reset, please ignore this email.</p>'
                    .'<p>ដោយក្តីគោរព | Regards,<br><strong>សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល</strong><br>{{ app }}</p>',
            ],
            default => [],
        };
    }

    public function headerTitle(): string
    {
        return filled($this->header_title)
            ? (string) $this->header_title
            : (string) (self::defaults($this->key)['header_title'] ?? config('app.name'));
    }

    /**
     * Full path of the uploaded logo; built-in emails use the UHS logo until one is uploaded.
     */
    public function logoFullPath(): ?string
    {
        if (filled($this->logo_path) && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->path($this->logo_path);
        }

        $default = public_path(self::DEFAULT_LOGO);

        return $this->isBuiltIn() && is_file($default) ? $default : null;
    }

    /**
     * The designed body as HTML with {{ variables }} filled in. Values are
     * escaped, except those in $html (for example the button).
     */
    public function renderBody(array $variables = [], array $html = []): string
    {
        $values = array_map(fn ($value): string => e((string) $value), $variables);

        return self::fillVariables($this->raw('body'), [...$values, ...$html]);
    }
}
