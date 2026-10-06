<?php

namespace App\Services\Broadcasts;

use App\Enums\BotLanguage;
use App\Enums\BroadcastType;
use App\Models\Broadcast;
use App\Models\User;

final class CampaignValidator
{
    public function validate(Broadcast $broadcast): void
    {
        if ($broadcast->expires_at?->isPast()) {
            throw new InvalidBroadcastTransition('This campaign has expired');
        }
        if ($broadcast->type === BroadcastType::Poll) {
            $poll = $broadcast->poll;
            if (! $poll) {
                throw new InvalidBroadcastTransition('Add a poll question and options');
            }
            foreach (['en', 'am'] as $lang) {
                $options = $poll->options[$lang] ?? [];
                if ($options && (count($options) < 2 || count($options) > 10)) {
                    throw new InvalidBroadcastTransition('Polls need 2–10 options');
                }
            }

            return;
        }
        try {
            foreach (['a', 'b'] as $variant) {
                $broadcast->setAttribute('delivery_variant', $variant);
                foreach (BotLanguage::cases() as $lang) {
                    $message = app(BroadcastRenderer::class)->renderForLanguage($broadcast, $lang, new User(['first_name' => str_repeat('W', 64), 'language' => $lang->value]));
                    $limit = $message->media ? 1024 : 4096;
                    if (mb_strlen($message->text ?? '') > $limit) {
                        throw new InvalidBroadcastTransition('Shorten the '.$lang->value.' message: rendered limit is '.$limit.' characters');
                    }
                    if (! $message->media && ! filled($message->text)) {
                        throw new InvalidBroadcastTransition('Add message content');
                    }
                }
            }
        } finally {
            $broadcast->offsetUnset('delivery_variant');
        }
    }
}
