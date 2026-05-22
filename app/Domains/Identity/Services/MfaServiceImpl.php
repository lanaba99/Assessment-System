<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\MfaService;
use App\Domains\Identity\DTOs\MfaEnrollmentResult;
use App\Domains\Identity\Exceptions\MfaDeviceNotFoundException;
use App\Domains\Identity\Models\MfaDevice;
use App\Domains\Identity\Repositories\MfaDeviceRepository;
use App\Domains\Identity\Repositories\SecurityPolicyRepository;
use App\Domains\Identity\Repositories\UserRepository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MfaServiceImpl implements MfaService
{
    private const DEVICE_TYPE_TOTP = 'totp';

    private const DEVICE_TYPE_BACKUP = 'backup_code';

    private const TOTP_SECRET_BYTES = 20;

    private const TOTP_DIGITS = 6;

    private const TOTP_PERIOD_SECONDS = 30;

    /** Number of ±period windows accepted to tolerate clock drift. */
    private const TOTP_DRIFT_WINDOWS = 1;

    private const BACKUP_CODE_COUNT = 10;

    private const BACKUP_CODE_BYTES = 5;

    public function __construct(
        private readonly MfaDeviceRepository $devices,
        private readonly SecurityPolicyRepository $policies,
        private readonly UserRepository $users,
        private readonly Hasher $hasher,
    ) {
    }

    public function enrollDevice(
        string $tenantId,
        string $userId,
        string $deviceType,
        ?string $deviceName,
    ): MfaEnrollmentResult {
        if ($deviceType !== self::DEVICE_TYPE_TOTP) {
            // SMS/email/push are out of scope for this implementation —
            // they need separate carriers (Twilio, mailer, push provider).
            throw new \InvalidArgumentException(
                "Device type '{$deviceType}' is not supported. Only 'totp' is implemented."
            );
        }

        return DB::transaction(function () use ($tenantId, $userId, $deviceName): MfaEnrollmentResult {
            $secret = $this->generateBase32Secret();

            $device = $this->devices->create($tenantId, $userId, [
                'device_type' => self::DEVICE_TYPE_TOTP,
                'device_identifier' => null,
                'device_name' => $deviceName,
                'secret_key_hash' => Crypt::encryptString($secret),
                'is_backup_code' => false,
                'is_verified' => false,
                'backup_codes_count' => 0,
            ]);

            $provisioningUri = $this->buildProvisioningUri($tenantId, $userId, $secret);

            return new MfaEnrollmentResult(
                deviceId: (string) $device->mfa_device_id,
                userId: $userId,
                deviceType: self::DEVICE_TYPE_TOTP,
                provisioningUri: $provisioningUri,
                backupCodes: null,
                requiresVerification: true,
            );
        });
    }

    public function verifyEnrollment(
        string $tenantId,
        string $userId,
        string $deviceId,
        string $oneTimeCode,
    ): bool {
        return DB::transaction(function () use ($tenantId, $userId, $deviceId, $oneTimeCode): bool {
            $device = $this->devices->findById($tenantId, $deviceId);

            if ($device === null || (string) $device->user_id !== $userId) {
                throw MfaDeviceNotFoundException::forUser($userId, $deviceId);
            }

            if ((string) $device->device_type !== self::DEVICE_TYPE_TOTP) {
                return false;
            }

            $secret = $this->decryptSecret((string) $device->secret_key_hash);

            if ($secret === null || ! $this->verifyTotp($secret, $oneTimeCode)) {
                return false;
            }

            $this->devices->markVerified($device);

            return true;
        });
    }

    public function verifyToken(string $tenantId, string $userId, string $oneTimeCode): bool
    {
        return DB::transaction(function () use ($tenantId, $userId, $oneTimeCode): bool {
            $devices = $this->devices->listVerifiedForUser($tenantId, $userId);

            foreach ($devices as $device) {
                if ((string) $device->device_type === self::DEVICE_TYPE_TOTP
                    && (bool) $device->is_backup_code === false) {
                    $secret = $this->decryptSecret((string) $device->secret_key_hash);

                    if ($secret !== null && $this->verifyTotp($secret, $oneTimeCode)) {
                        $this->devices->recordLastUse($device);

                        return true;
                    }
                }
            }

            foreach ($devices as $device) {
                if ((bool) $device->is_backup_code === true
                    && $this->hasher->check($oneTimeCode, (string) $device->secret_key_hash)) {
                    // Consume the backup code — single-use only.
                    $this->devices->delete($tenantId, $userId, (string) $device->mfa_device_id);

                    return true;
                }
            }

            return false;
        });
    }

    public function disableDevice(
        string $tenantId,
        string $userId,
        string $deviceId,
        string $disabledByUserId,
    ): bool {
        return $this->devices->delete($tenantId, $userId, $deviceId);
    }

    /**
     * @return array<int, string>
     */
    public function regenerateBackupCodes(string $tenantId, string $userId): array
    {
        return DB::transaction(function () use ($tenantId, $userId): array {
            $existing = $this->devices->listForUser($tenantId, $userId);
            foreach ($existing as $device) {
                if ((bool) $device->is_backup_code === true) {
                    $this->devices->delete($tenantId, $userId, (string) $device->mfa_device_id);
                }
            }

            $plaintextCodes = [];
            for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
                $code = $this->generateBackupCode();
                $plaintextCodes[] = $code;

                $this->devices->create($tenantId, $userId, [
                    'device_type' => self::DEVICE_TYPE_BACKUP,
                    'device_identifier' => null,
                    'device_name' => 'Backup code',
                    'secret_key_hash' => $this->hasher->make($code),
                    'is_backup_code' => true,
                    'is_verified' => true,
                    'backup_codes_count' => 1,
                    'verified_at' => now(),
                ]);
            }

            return $plaintextCodes;
        });
    }

    private function generateBase32Secret(): string
    {
        $bytes = random_bytes(self::TOTP_SECRET_BYTES);

        return $this->base32Encode($bytes);
    }

    private function generateBackupCode(): string
    {
        // 10 hex chars, dash-separated for readability: e.g. "a3f2-8b91-c"
        $hex = bin2hex(random_bytes(self::BACKUP_CODE_BYTES));

        return substr($hex, 0, 4) . '-' . substr($hex, 4, 4) . '-' . substr($hex, 8);
    }

    private function buildProvisioningUri(string $tenantId, string $userId, string $secret): string
    {
        $issuer = rawurlencode(config('app.name', 'EAE'));
        $accountLabel = rawurlencode($userId . '@' . $tenantId);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            $issuer,
            $accountLabel,
            $secret,
            $issuer,
            self::TOTP_DIGITS,
            self::TOTP_PERIOD_SECONDS,
        );
    }

    private function decryptSecret(string $encrypted): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * RFC 6238 TOTP verifier. Accepts the current period ±DRIFT windows
     * to tolerate small client/server clock skew.
     */
    private function verifyTotp(string $base32Secret, string $providedCode): bool
    {
        $providedCode = preg_replace('/\D/', '', $providedCode) ?? '';

        if (strlen($providedCode) !== self::TOTP_DIGITS) {
            return false;
        }

        $secretBinary = $this->base32Decode($base32Secret);
        $currentCounter = intdiv(time(), self::TOTP_PERIOD_SECONDS);

        for ($offset = -self::TOTP_DRIFT_WINDOWS; $offset <= self::TOTP_DRIFT_WINDOWS; $offset++) {
            $expected = $this->computeHotp($secretBinary, $currentCounter + $offset);

            if (hash_equals($expected, $providedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * HOTP per RFC 4226 — the underlying primitive of TOTP.
     */
    private function computeHotp(string $secretBinary, int $counter): string
    {
        $counterBinary = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $modulus = 10 ** self::TOTP_DIGITS;

        return str_pad((string) ($value % $modulus), self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bitsInBuffer = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bitsInBuffer += 8;

            while ($bitsInBuffer >= 5) {
                $bitsInBuffer -= 5;
                $output .= $alphabet[($buffer >> $bitsInBuffer) & 0x1F];
            }
        }

        if ($bitsInBuffer > 0) {
            $output .= $alphabet[($buffer << (5 - $bitsInBuffer)) & 0x1F];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(rtrim($encoded, '='));
        $output = '';
        $buffer = 0;
        $bitsInBuffer = 0;

        foreach (str_split($encoded) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $position;
            $bitsInBuffer += 5;

            if ($bitsInBuffer >= 8) {
                $bitsInBuffer -= 8;
                $output .= chr(($buffer >> $bitsInBuffer) & 0xFF);
            }
        }

        return $output;
    }
}
