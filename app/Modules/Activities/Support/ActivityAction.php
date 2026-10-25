<?php

namespace App\Modules\Activities\Support;

/**
 * One activity page action contributed by an activity type (ARCH-009):
 * a stable key (data-test activity-action-<key>), the link target and the
 * translation key of its label.
 */
final readonly class ActivityAction
{
    public function __construct(
        public string $key,
        public string $href,
        public string $label,
    ) {}

    /**
     * @return array{key: string, href: string, label: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'href' => $this->href, 'label' => $this->label];
    }
}
