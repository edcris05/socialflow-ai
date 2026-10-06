<?php

namespace App\Services\Publishing;

use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\PublicMediaHosting;
use App\Models\ScheduledPublication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DueScheduledPublicationFinder
{
    /** @return Collection<int, ScheduledPublication> */
    public function find(int $limit): Collection
    {
        $freshAfter = now()->subMinutes(
            max(1, (int) config('services.publication_media.preflight_fresh_minutes', 15)),
        );

        return ScheduledPublication::query()
            ->where('status', ScheduledPublication::STATUS_SCHEDULED)
            ->where('scheduled_for', '<=', now())
            ->whereDoesntHave('publicationAttempts', function (Builder $attempts): void {
                $attempts
                    ->where('provider', 'meta')
                    ->whereIn('status', PublicationAttempt::reentryBlockingStatuses());
            })
            ->whereHas('draft', function (Builder $drafts) use ($freshAfter): void {
                $drafts
                    ->whereColumn('drafts.brand_id', 'scheduled_publications.brand_id')
                    ->where('drafts.status', Draft::STATUS_APPROVED)
                    ->whereHas('currentPublicationMedia', function (Builder $media) use ($freshAfter): void {
                        $media
                            ->whereColumn('publication_media.brand_id', 'scheduled_publications.brand_id')
                            ->where('publication_media.status', PublicationMedia::STATUS_APPROVED)
                            ->where('publication_media.type', PublicationMedia::TYPE_IMAGE)
                            ->where('publication_media.mime_type', 'image/jpeg')
                            ->where('publication_media.preflight_status', PublicationMedia::PREFLIGHT_PASSED)
                            ->where('publication_media.preflight_checked_at', '>=', $freshAfter)
                            ->where(function (Builder $publicMedia): void {
                                $publicMedia
                                    ->where('publication_media.public_url', 'like', 'https://%')
                                    ->orWhereHas('publicHosting', function (Builder $hosting): void {
                                        $hosting
                                            ->where('status', PublicMediaHosting::STATUS_HOSTED)
                                            ->where('public_url', 'like', 'https://%');
                                    });
                            });
                    });
            })
            ->whereHas('brand.metaConnection', function (Builder $connections): void {
                $connections
                    ->where('status', MetaConnection::STATUS_VERIFIED)
                    ->whereNotNull('access_token')
                    ->where('access_token', '<>', '')
                    ->whereNotNull('instagram_account_id')
                    ->where('instagram_account_id', '<>', '');
            })
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->limit($this->boundedLimit($limit))
            ->get();
    }

    public function boundedLimit(int $limit): int
    {
        $maximum = max(1, (int) config('services.scheduled_publishing.max_limit', 100));

        return min(max(1, $limit), $maximum);
    }
}
