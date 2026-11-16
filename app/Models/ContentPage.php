<?php

namespace App\Models;

use Database\Factories\ContentPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Public text pages (terms, privacy policy, partnership agreement, help) that the admin can edit.
 * Until the admin saves a version, the default text in resources/content/{slug}.md is shown.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $editor
 */
#[Fillable(['slug', 'title', 'body', 'updated_by'])]
class ContentPage extends Model
{
    /** @use HasFactory<ContentPageFactory> */
    use HasFactory;

    /**
     * Editable pages: slug => [title, public route name].
     *
     * @var array<string, array{title: string, route: string}>
     */
    public const PAGES = [
        'syarat-ketentuan' => ['title' => 'Syarat & Ketentuan', 'route' => 'pages.terms'],
        'kebijakan-privasi' => ['title' => 'Kebijakan Privasi', 'route' => 'pages.privacy'],
        'perjanjian-kemitraan' => ['title' => 'Perjanjian Kemitraan Teknisi', 'route' => 'pages.partnership'],
        'bantuan' => ['title' => 'Bantuan & Pertanyaan Umum', 'route' => 'pages.help'],
    ];

    /**
     * The saved page, or an unsaved one holding the default text.
     */
    public static function findOrDefault(string $slug): self
    {
        abort_unless(array_key_exists($slug, self::PAGES), 404);

        return self::firstWhere('slug', $slug) ?? new self([
            'slug' => $slug,
            'title' => self::PAGES[$slug]['title'],
            'body' => self::defaultBody($slug),
        ]);
    }

    public static function defaultBody(string $slug): string
    {
        $path = resource_path("content/{$slug}.md");

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * Body rendered from Markdown. Raw HTML from the editor is stripped.
     */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
