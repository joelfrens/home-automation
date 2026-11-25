<?php

namespace Joelfrens\HomeAutomation\Reminders\Notifications;

/**
 * One ntfy push: title + text, how loud (priority 1-5), emoji tags and where a tap goes.
 * See https://docs.ntfy.sh/publish/#publish-as-json
 */
final class NtfyMessage
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly int $priority = 3,
        public readonly array $tags = [],
        public readonly ?string $click = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'message' => $this->message,
            'priority' => $this->priority,
            'tags' => $this->tags,
            'click' => $this->click,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
