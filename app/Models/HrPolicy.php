<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPolicy extends Model
{
    protected $fillable = [
        'title', 'category', 'type', 'body',
        'file_path', 'file_original_name', 'link_url',
        'is_published', 'published_at',
        'is_pinned', 'effective_date', 'expiry_date',
        'requires_acknowledgment', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'published_at' => 'datetime',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function views()
    {
        return $this->hasMany(HrPolicyView::class);
    }

    public function categoryMeta(): array
    {
        return config("policy_categories.{$this->category}") ?? config('policy_categories.default');
    }

    public function statusLabel(): string
    {
        $today = now()->startOfDay();

        if ($this->expiry_date && $this->expiry_date->lt($today)) {
            return 'expired';
        }

        if ($this->effective_date && $this->effective_date->gt($today)) {
            return 'upcoming';
        }

        return 'active';
    }

    public function isNew(): bool
    {
        return $this->published_at && $this->published_at->gt(now()->subDays(7));
    }

    public function readingTime(): ?string
    {
        if ($this->type !== 'text' || !$this->body) {
            return null;
        }

        $words = str_word_count(strip_tags($this->body));
        $minutes = max(1, (int) ceil($words / 200));

        return "~{$minutes} min read";
    }

    public function tableOfContents(): array
    {
        if ($this->type !== 'text' || !$this->body) {
            return [];
        }

        // Matches h1/h2/h3 to mirror the Quill editor's header toolbar ({header: [1,2,3,false]}).
        // Previously only matched h1/h2, silently dropping any H3 subheadings from the TOC.
        preg_match_all('/<h([123])[^>]*>(.*?)<\/h\1>/i', $this->body, $matches, PREG_SET_ORDER);

        return collect($matches)->map(fn ($m) => [
            'level' => (int) $m[1],
            'text' => trim(strip_tags($m[2])),
            'slug' => \Illuminate\Support\Str::slug(strip_tags($m[2])),
        ])->toArray();
    }

    /**
     * Returns the policy body with id="..." injected into each h1/h2/h3 tag, using the
     * exact same slugs as tableOfContents(). Previously the view relied on a separate
     * JS slugifier running client-side to assign these ids, which used a different
     * algorithm than Str::slug() (no transliteration, different punctuation handling) —
     * any heading where the two disagreed produced a TOC link that scrolled nowhere.
     * Generating both from the same PHP call guarantees they always match, and it also
     * means the anchors work even if JS is disabled or hasn't finished running yet.
     */
    /**
     * The policy body, safe to print unescaped to every member of staff.
     *
     * The body is HTML from the rich-text editor, but the server receives
     * whatever is posted in its place, and this used to be printed exactly as
     * stored. Script or an event handler saved into a policy ran in the browser
     * of every employee who opened it — which is how one compromised HR session
     * becomes every staff member's session.
     *
     * So it is reduced to the formatting the editor can actually produce, and
     * nothing else survives: no scripts, no event handlers, no javascript:
     * links, no styles or frames. Done here, at the point of output, so policies
     * saved before this existed are covered too.
     */
    public function renderedBody(): ?string
    {
        if ($this->type !== 'text' || ! $this->body) {
            return $this->body;
        }

        $clean = self::sanitizer()->sanitize($this->body);

        // Anchors for the table of contents, added to markup that is already
        // clean. The slug is letters, digits and hyphens only.
        return preg_replace_callback(
            '/<h([123])([^>]*)>(.*?)<\/h\1>/is',
            function ($m) {
                $slug = \Illuminate\Support\Str::slug(strip_tags($m[3]));

                return "<h{$m[1]}{$m[2]} id=\"{$slug}\">{$m[3]}</h{$m[1]}>";
            },
            $clean
        );
    }

    /** What the rich-text editor produces, and nothing more. */
    private static function sanitizer(): \Symfony\Component\HtmlSanitizer\HtmlSanitizer
    {
        static $sanitizer;

        if ($sanitizer) {
            return $sanitizer;
        }

        $config = (new \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig())
            ->allowElement('p', ['class'])
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('span', ['class'])
            ->allowElement('h1', ['class'])
            ->allowElement('h2', ['class'])
            ->allowElement('h3', ['class'])
            ->allowElement('h4', ['class'])
            ->allowElement('blockquote', ['class'])
            ->allowElement('pre', ['class'])
            ->allowElement('code')
            ->allowElement('ol', ['class'])
            ->allowElement('ul', ['class'])
            // Quill 2 marks a bullet list as <ol><li data-list="bullet">.
            ->allowElement('li', ['class', 'data-list'])
            ->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(false)
            // Links open away from the policy without handing it the tab.
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        return $sanitizer = new \Symfony\Component\HtmlSanitizer\HtmlSanitizer($config);
    }
}