<?php

namespace App\Services;

use App\Models\Page;

class CmsService
{
    /**
     * Return CMS content for the app landing page.
     *
     * @return array|null|string
     */
    public function getContent()
    {
        try {
            $page = Page::where('key', 'welcome')->first();
            return $page?->content ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
