<?php

namespace App\Services\Email;

use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class StaffInvitationService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function createAndSend(User $staff, User $inviter): StaffInvitation
    {
        if (! $this->settings->featureEnabled('feature_invitations')) {
            throw new RuntimeException('Staff invitation emails are currently disabled in Email Settings.');
        }

        if (! filled($staff->email)) {
            throw new RuntimeException('A valid staff email address is required to send an invitation.');
        }

        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);
        $expireHours = max(1, (int) config('email.invitation_expire_hours', 48));
        $expiresAt = now('UTC')->addHours($expireHours);

        $invitation = DB::transaction(function () use ($staff, $inviter, $tokenHash, $expiresAt): StaffInvitation {
            StaffInvitation::query()
                ->where('user_id', $staff->id)
                ->where('status', StaffInvitation::STATUS_PENDING)
                ->update([
                    'status' => StaffInvitation::STATUS_REVOKED,
                    'revoked_at' => now('UTC'),
                ]);

            return StaffInvitation::query()->create([
                'user_id' => $staff->id,
                'email' => mb_strtolower(trim((string) $staff->email)),
                'token_hash' => $tokenHash,
                'status' => StaffInvitation::STATUS_PENDING,
                'invited_by' => $inviter->id,
                'expires_at' => $expiresAt,
            ]);
        });

        $this->transactional->queue(
            messageKey: 'staff-invitation:'.$invitation->id,
            purpose: 'staff_invitation',
            recipientEmail: $invitation->email,
            recipientName: $staff->full_name,
            subject: 'You Are Invited to Artisan Brew Co.',
            templateName: 'staff_invitation',
            templateData: [
                'fullName' => $staff->full_name,
                'username' => $staff->username,
                'roleLabel' => ucfirst($staff->role),
                'inviterName' => $inviter->full_name,
                'acceptUrl' => url(route('invitations.show', ['token' => $rawToken], false)),
                'expireHours' => $expireHours,
            ],
            options: [
                'related_type' => 'staff_invitation',
                'related_id' => $invitation->id,
                'initiated_by' => $inviter->id,
                'sensitive' => true,
                'expires_at' => $expiresAt,
                'queue' => config('email.queues.priority', 'emails-priority'),
            ]
        );

        return $invitation;
    }

    public function revokePendingForUser(User $staff): int
    {
        return StaffInvitation::query()
            ->where('user_id', $staff->id)
            ->where('status', StaffInvitation::STATUS_PENDING)
            ->update([
                'status' => StaffInvitation::STATUS_REVOKED,
                'revoked_at' => now('UTC'),
            ]);
    }

    public function findByRawToken(string $rawToken): ?StaffInvitation
    {
        $hash = hash('sha256', trim($rawToken));

        return StaffInvitation::query()
            ->with('user')
            ->where('token_hash', $hash)
            ->first();
    }

    public function accept(string $rawToken, string $password): ?User
    {
        $hash = hash('sha256', trim($rawToken));

        return DB::transaction(function () use ($hash, $password): ?User {
            $invitation = StaffInvitation::query()
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $invitation || $invitation->status !== StaffInvitation::STATUS_PENDING) {
                return null;
            }

            if ($invitation->expires_at === null || $invitation->expires_at->isPast()) {
                $invitation->status = StaffInvitation::STATUS_EXPIRED;
                $invitation->save();

                return null;
            }

            $user = User::query()->whereKey($invitation->user_id)->lockForUpdate()->first();
            if (! $user || $user->deleted_at !== null) {
                return null;
            }

            $user->password = Hash::make($password);
            $user->email = $invitation->email;
            $user->email_verified_at = now('UTC');
            $user->is_active = true;
            $user->save();

            $invitation->status = StaffInvitation::STATUS_ACCEPTED;
            $invitation->accepted_at = now('UTC');
            $invitation->save();

            return $user;
        });
    }
}
