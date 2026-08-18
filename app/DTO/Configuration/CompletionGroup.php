<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

final readonly class CompletionGroup implements Arrayable
{
    /**
     * @param  Completion[]  $completions
     */
    public function __construct(
        public int $group,
        public array $completions,
    ) {}

    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'completions' => array_map(fn (Completion $c) => $c->toArray(), $this->completions),
        ];
    }

    /**
     * Extract unique active completions from completion groups.
     *
     * @param  CompletionGroup[]  $completionGroups
     * @return array<string>
     */
    public static function extractCompletions(array $completionGroups): array
    {
        return array_values(array_unique(
            array_merge(...array_map(
                fn (CompletionGroup $group) => array_map(
                    fn (Completion $c) => $c->product,
                    array_filter(
                        $group->completions,
                        fn (Completion $c) => $c->isActive()
                    )
                ),
                $completionGroups
            ))
        ));
    }
}
