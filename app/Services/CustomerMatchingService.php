<?php

namespace App\Services;

use App\Models\GhlConnection;
use App\Models\LaundrycardSourceRecord;
use App\Support\Normalizer;

/**
 * Matches a source customer to an existing contact inside ONE destination location.
 * Only email and normalised phone are used - never name alone.
 */
class CustomerMatchingService
{
    public const NONE = 'none';
    public const SINGLE = 'single';
    public const CONFLICT = 'conflict';

    public function __construct(private HighLevelClient $ghl)
    {
    }

    /** @return array{result:string,contact:?array,matched_on:?string,reason:?string} */
    public function match(GhlConnection $connection, LaundrycardSourceRecord $record): array
    {
        $email = Normalizer::email($record->customer_email);
        $phone = Normalizer::phone($record->customer_phone);

        $byEmail = $email ? $this->ghl->findDuplicate($connection, $email, null) : null;
        $byPhone = $phone ? $this->ghl->findDuplicate($connection, null, $phone) : null;

        // Sanity check: a returned contact must really carry one of our identifiers.
        foreach ([$byEmail, $byPhone] as $found) {
            if ($found && ! $this->confirms($found, $email, $phone)) {
                return ['result' => self::CONFLICT, 'contact' => null, 'matched_on' => null, 'reason' => 'match_not_verifiable'];
            }
        }

        if ($byEmail && $byPhone && $byEmail['id'] !== $byPhone['id']) {
            return ['result' => self::CONFLICT, 'contact' => null, 'matched_on' => 'email+phone', 'reason' => 'email_and_phone_match_different_contacts'];
        }
        if ($byEmail || $byPhone) {
            return ['result' => self::SINGLE, 'contact' => $byEmail ?? $byPhone, 'matched_on' => $byEmail && $byPhone ? 'email+phone' : ($byEmail ? 'email' : 'phone'), 'reason' => null];
        }

        return ['result' => self::NONE, 'contact' => null, 'matched_on' => null, 'reason' => null];
    }

    private function confirms(array $contact, ?string $email, ?string $phone): bool
    {
        $ce = Normalizer::email($contact['email'] ?? null);
        $cp = Normalizer::phone($contact['phone'] ?? null);
        // If GHL omitted both fields we cannot disprove the match.
        if ($ce === null && $cp === null) {
            return true;
        }

        return ($email && $ce === $email) || ($phone && $cp === $phone);
    }
}
