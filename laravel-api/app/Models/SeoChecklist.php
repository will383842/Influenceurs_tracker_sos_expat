<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoChecklist extends Model
{
    protected $fillable = [
        'article_id',
        // On-Page
        'has_single_h1', 'h1_contains_keyword',
        'title_tag_length', 'title_tag_contains_keyword',
        'meta_desc_length', 'meta_desc_contains_cta',
        'keyword_in_first_paragraph', 'keyword_density_ok',
        'heading_hierarchy_valid', 'has_table_or_list',
        // Structured Data
        'has_article_schema', 'has_faq_schema', 'has_breadcrumb_schema',
        'has_speakable_schema', 'has_howto_schema', 'json_ld_valid',
        // E-E-A-T
        'has_author_box', 'has_sources_cited',
        'has_date_published', 'has_date_modified', 'has_official_links',
        // Links
        'internal_links_count', 'external_links_count',
        'official_links_count', 'broken_links_count',
        // Featured Snippets
        'has_definition_paragraph', 'has_numbered_steps', 'has_comparison_table',
        // AEO
        'has_speakable_content', 'has_direct_answers', 'paa_questions_covered',
        // Images
        'all_images_have_alt', 'featured_image_has_keyword', 'images_count',
        // Translation
        'hreflang_complete', 'translations_count',
        // Score
        'overall_checklist_score',
    ];

    protected $casts = [
        // On-Page booleans
        'has_single_h1'              => 'boolean',
        'h1_contains_keyword'        => 'boolean',
        'title_tag_length'           => 'integer',
        'title_tag_contains_keyword' => 'boolean',
        'meta_desc_length'           => 'integer',
        'meta_desc_contains_cta'     => 'boolean',
        'keyword_in_first_paragraph' => 'boolean',
        'keyword_density_ok'         => 'boolean',
        'heading_hierarchy_valid'    => 'boolean',
        'has_table_or_list'          => 'boolean',
        // Structured Data booleans
        'has_article_schema'         => 'boolean',
        'has_faq_schema'             => 'boolean',
        'has_breadcrumb_schema'      => 'boolean',
        'has_speakable_schema'       => 'boolean',
        'has_howto_schema'           => 'boolean',
        'json_ld_valid'              => 'boolean',
        // E-E-A-T booleans
        'has_author_box'             => 'boolean',
        'has_sources_cited'          => 'boolean',
        'has_date_published'         => 'boolean',
        'has_date_modified'          => 'boolean',
        'has_official_links'         => 'boolean',
        // Links integers
        'internal_links_count'       => 'integer',
        'external_links_count'       => 'integer',
        'official_links_count'       => 'integer',
        'broken_links_count'         => 'integer',
        // Featured Snippets booleans
        'has_definition_paragraph'   => 'boolean',
        'has_numbered_steps'         => 'boolean',
        'has_comparison_table'       => 'boolean',
        // AEO
        'has_speakable_content'      => 'boolean',
        'has_direct_answers'         => 'boolean',
        'paa_questions_covered'      => 'integer',
        // Images
        'all_images_have_alt'        => 'boolean',
        'featured_image_has_keyword' => 'boolean',
        'images_count'               => 'integer',
        // Translation
        'hreflang_complete'          => 'boolean',
        'translations_count'         => 'integer',
        // Score
        'overall_checklist_score'    => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'article_id');
    }
}
