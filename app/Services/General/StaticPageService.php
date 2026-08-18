<?php

namespace App\Services\General;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Marvel\Database\Models\StaticPage;
use Marvel\Database\Models\StaticSection;

class StaticPageService
{
    /**
     * List all static pages with their sections eager loaded.
     */
    public function getAll(): Collection
    {
        return StaticPage::with('staticSections')->get();
    }

    /**
     * Retrieve a static page by slug with sections eager loaded.
     */
    public function getBySlug(string $slug): ?StaticPage
    {
        return StaticPage::with('staticSections')->where('slug', $slug)->first();
    }

    /**
     * Retrieve a static page by slug or fail with a 404-equivalent exception.
     */
    public function getPageOrFail(string $slug): StaticPage
    {
        $page = $this->getBySlug($slug);

        if (!$page) {
            throw (new ModelNotFoundException())->setModel(StaticPage::class);
        }

        return $page;
    }

    /**
     * Update a fixed static page (title / is_active). Page identity is never
     * changed through this path.
     */
    public function updatePage(StaticPage $page, array $data): StaticPage
    {
        $page->update($data);

        return $page->load('staticSections');
    }

    /**
     * Create a section belonging to the given page.
     */
    public function createSection(StaticPage $page, array $data): StaticSection
    {
        return $page->staticSections()->create($data);
    }

    /**
     * Update a section only when it belongs to the given page.
     *
     * A section owned by another page is treated as not found so its existence
     * is never leaked through a different page's route.
     */
    public function updateSection(StaticPage $page, StaticSection $section, array $data): StaticSection
    {
        $this->assertSectionBelongsToPage($page, $section);
        $section->update($data);

        return $section;
    }

    /**
     * Delete a section only when it belongs to the given page.
     */
    public function deleteSection(StaticPage $page, StaticSection $section): void
    {
        $this->assertSectionBelongsToPage($page, $section);
        $section->delete();
    }

    /**
     * Reorder sections of the given page only.
     *
     * Every supplied id must belong to the page, otherwise a 404-equivalent
     * exception is raised. The underlying update is additionally scoped by
     * static_page_id so a bypass can never touch another page's rows.
     */
    public function reorderSections(StaticPage $page, array $sectionIds): void
    {
        $existingCount = StaticSection::query()
            ->where('static_page_id', $page->id)
            ->whereIn('id', $sectionIds)
            ->count();

        if ($existingCount !== count(array_unique($sectionIds))) {
            throw (new ModelNotFoundException())->setModel(StaticSection::class);
        }

        StaticSection::setNewOrder(
            $sectionIds,
            1,
            'id',
            function ($query) use ($page) {
                $query->where('static_page_id', $page->id);
            }
        );
    }

    private function assertSectionBelongsToPage(StaticPage $page, StaticSection $section): void
    {
        if ((int) $section->static_page_id !== (int) $page->id) {
            throw (new ModelNotFoundException())->setModel(StaticSection::class);
        }
    }
}