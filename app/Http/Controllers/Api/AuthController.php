<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['patient', 'doctor', 'admin'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'height' => ['required', 'integer', 'min:80', 'max:230'],
            'date_of_birth' => ['nullable', 'date'],
            'blood_type' => ['nullable', 'string', 'max:8'],
            'weight' => ['nullable', 'string', 'max:12'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'] ?? 'patient',
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'],
            'height' => $validated['height'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'blood_type' => $validated['blood_type'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'governorate' => $validated['governorate'] ?? null,
            'area' => $validated['area'] ?? null,
            'address' => $validated['address'] ?? null,
            'avatar' => $avatarPath,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string'],
            'role' => ['nullable', Rule::in(['patient', 'doctor', 'admin'])],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => ['Please provide email or phone number.'],
            ]);
        }

        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $identifier)->first()
            : User::where('phone', $identifier)->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is currently inactive.',
            ], 403);
        }

        if (!empty($validated['role']) && $user->role !== $validated['role']) {
            return response()->json([
                'message' => 'Role mismatch for this account.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => ['Email or phone is required.'],
            ]);
        }

        $isEmail = (bool) filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $channel = $isEmail ? 'email' : 'sms';
        $query = $isEmail ? User::where('email', Str::lower($identifier)) : User::where('phone', $identifier);
        $user = $query->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'identifier' => ['Account not found.'],
            ]);
        }

        PasswordResetOtp::where('identifier', $identifier)->delete();
        PasswordResetOtp::where('email', $identifier)->delete(); // backward compatibility

        $otp = (string) random_int(100000, 999999);
        PasswordResetOtp::create([
            'email' => $identifier, // keep legacy column populated
            'identifier' => $identifier,
            'channel' => $channel,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->sendOtp($identifier, $channel, $otp, 10);

        return response()->json([
            'message' => 'OTP sent successfully',
            'channel' => $channel,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'otp' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => ['Email or phone is required.'],
            ]);
        }
        /** @var PasswordResetOtp|null $record */
        $record = PasswordResetOtp::query()
            ->where(function ($q) use ($identifier) {
                $q->where('identifier', $identifier)->orWhere('email', $identifier);
            })
            ->valid()
            ->latest()
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'OTP expired or not found.',
            ], 422);
        }

        if ($record->attempts >= 5) {
            return response()->json([
                'message' => 'Too many attempts. Please request a new OTP.',
            ], 429);
        }

        $record->increment('attempts');

        if (!Hash::check($validated['otp'], $record->otp_hash)) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $record->update(['verified_at' => now()]);

        return response()->json([
            'message' => 'OTP verified successfully',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => ['Email or phone is required.'],
            ]);
        }

        /** @var PasswordResetOtp|null $record */
        $record = PasswordResetOtp::query()
            ->where(function ($q) use ($identifier) {
                $q->where('identifier', $identifier)->orWhere('email', $identifier);
            })
            ->valid()
            ->latest()
            ->first();
        if (!$record) {
            return response()->json([
                'message' => 'OTP expired or not found.',
            ], 422);
        }

        if ($record->attempts >= 5) {
            return response()->json([
                'message' => 'Too many attempts. Please request a new OTP.',
            ], 429);
        }

        $record->increment('attempts');

        if (!Hash::check($validated['otp'], $record->otp_hash)) {
            return response()->json([
                'message' => 'Invalid OTP.',
            ], 422);
        }

        if (!$record->verified_at) {
            $record->update(['verified_at' => now()]);
        }

        $isEmail = (bool) filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $user = $isEmail
            ? User::where('email', Str::lower($identifier))->firstOrFail()
            : User::where('phone', $identifier)->firstOrFail();
        $user->update(['password' => $validated['password']]);
        $user->tokens()->delete();

        PasswordResetOtp::where(function ($q) use ($identifier) {
            $q->where('identifier', $identifier)->orWhere('email', $identifier);
        })->delete();

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    private function sendOtp(string $identifier, string $channel, string $otp, int $expiresInMinutes): void
    {
        if ($channel === 'email') {
            try {
                Mail::to(Str::lower($identifier))->send(new PasswordResetOtpMail($otp, $expiresInMinutes));
                return;
            } catch (\Throwable $e) {
                Log::error('Failed to send password reset OTP email', [
                    'identifier' => $identifier,
                    'error' => $e->getMessage(),
                ]);
                throw ValidationException::withMessages([
                    'identifier' => ['Could not send OTP email. Please check mail settings.'],
                ]);
            }
        }

        $smsUrl = (string) config('services.sms.url');
        $smsApiKey = (string) config('services.sms.api_key');
        $smsSender = (string) env('SMS_PROVIDER_SENDER', 'Lesahtak');

        if ($smsUrl === '' || $smsApiKey === '') {
            Log::warning('SMS OTP requested but SMS provider is not configured', ['identifier' => $identifier]);
            throw ValidationException::withMessages([
                'identifier' => ['SMS OTP is not configured yet. Set SMS_PROVIDER_URL and SMS_PROVIDER_API_KEY (or AT_API_KEY).'],
            ]);
        }

        $message = "Your Lesahtak OTP is {$otp}. It expires in {$expiresInMinutes} minutes.";
        $destination = $this->normalizePhoneForInternationalSms($identifier);

        $driver = (string) config('services.sms.driver', 'auto');
        if ($driver === 'auto') {
            $driver = str_contains($smsUrl, 'africastalking.com') ? 'africastalking' : 'generic_json';
        }

        if ($driver === 'africastalking') {
            $this->sendSmsViaAfricasTalking($smsUrl, $smsApiKey, $destination, $message);
        } else {
            $this->sendSmsViaGenericJson($smsUrl, $smsApiKey, $smsSender, $destination, $message);
        }
    }

    /**
     * تحويل الرقم لصيغة دولية حيثما أمكن (مثال مصر: 01xxxxxxxxx → +201xxxxxxxxx).
     */
    private function normalizePhoneForInternationalSms(string $identifier): string
    {
        $s = preg_replace('/\s+/', '', trim($identifier));
        if ($s === '') {
            return $identifier;
        }
        if (str_starts_with($s, '+')) {
            return $s;
        }
        if (preg_match('/^0(1\d{9})$/', $s)) {
            return '+20'.substr($s, 1);
        }
        if (preg_match('/^(1\d{9})$/', $s)) {
            return '+20'.$s;
        }

        return $s;
    }

    /**
     * Africa's Talking: POST form-urlencoded، التوثيق عبر header apiKey، حقول username، to، message، من (اختياري).
     *
     * @see https://developers.africastalking.com/docs/sms/sending
     */
    private function sendSmsViaAfricasTalking(string $url, string $apiKey, string $to, string $message): void
    {
        $username = (string) config('services.sms.username', 'sandbox');
        $from = trim((string) config('services.sms.from', ''));
        $tryWithoutSenderFirst = (bool) config('services.sms.at_try_without_sender_first', true);

        // غالبًا فشل الإرسال لأن ShortCode / alphanumeric غير معتمد لحسابك — نبدأ بدون from إذا SMS_AT_TRY_WITHOUT_SENDER_FIRST=true.
        /** @var list<bool> */
        $attemptIncludeSender = [];

        if ($tryWithoutSenderFirst) {
            $attemptIncludeSender[] = false;
            if ($from !== '') {
                $attemptIncludeSender[] = true;
            }
        } elseif ($from !== '') {
            $attemptIncludeSender[] = true;
            $attemptIncludeSender[] = false;
        } else {
            $attemptIncludeSender[] = false;
        }

        foreach ($attemptIncludeSender as $includeFrom) {
            $payload = [
                'username' => $username,
                'to' => $to,
                'message' => $message,
            ];
            if ($from !== '' && $includeFrom) {
                $payload['from'] = $from;
            }

            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    // أغلب الأمثلة والـ SDK تستخدم الاسم camelCase على الرغم من أن بعض الخوادم ترفع الحساسية
                    'apiKey' => $apiKey,
                ])
                ->asForm()
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::error('Failed to send SMS OTP (Africa\'s Talking HTTP)', [
                    'to' => $to,
                    'include_from' => $includeFrom && $from !== '',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                continue;
            }

            $json = $response->json();

            if ($this->africaTalkingApiIndicatesSuccess($json)) {
                if (! ($includeFrom && $from !== '')) {
                    Log::info('SMS OTP delivered via Africa\'s Talking without custom sender ID (or sender skipped).');
                }

                return;
            }

            $reason = $this->summarizeAfricaTalkingFailure($json);
            Log::warning('Africa\'s Talking rejected recipient batch', [
                'to' => $to,
                'include_from' => $includeFrom && $from !== '',
                'reason' => $reason,
                'json' => $json,
            ]);
        }

        throw ValidationException::withMessages([
            'identifier' => [
                'Could not send SMS OTP. Confirm SMS_PROVIDER_URL and AT_USERNAME fit your key (sandbox: api.sandbox… + username sandbox), check wallet balance at Africa\'s Talking, and try leaving SMS_PROVIDER_SENDER empty until your sender is approved.',
            ],
        ]);
    }

    /** @param  mixed  $json */
    private function africaTalkingApiIndicatesSuccess(?array $json): bool
    {
        if (! is_array($json)) {
            return false;
        }

        $smsBlock = $json['SMSMessageData'] ?? null;
        if (! is_array($smsBlock)) {
            return false;
        }

        $recipients = $smsBlock['Recipients'] ?? [];
        $summary = strtolower((string) ($smsBlock['Message'] ?? ''));

        if (! is_array($recipients) || $recipients === []) {
            return str_contains($summary, 'sent') || str_contains($summary, 'success');
        }

        foreach ($recipients as $r) {
            if (! is_array($r)) {
                continue;
            }
            $st = strtolower(trim((string) ($r['status'] ?? '')));
            $code = (int) ($r['statusCode'] ?? 0);

            if ($st === 'success' || $st === 'sent') {
                return true;
            }

            /**
             * 101 أكثر الأكواد شيوعًا للرسالة المرسلة. رفض المصادقة يأتي عادة بـ HTTP 4xx قبل JSON.
             *
             * @see http://help.africastalking.com/en/articles/742491
             */
            if (in_array($code, [100, 101, 102], true)) {
                return true;
            }
        }

        return false;
    }

    /** @param  mixed  $json */
    private function summarizeAfricaTalkingFailure(?array $json): string
    {
        if (! is_array($json)) {
            return 'empty_response';
        }
        $sms = $json['SMSMessageData'] ?? null;
        if (! is_array($sms)) {
            return 'no_sms_block';
        }
        $msg = trim((string) ($sms['Message'] ?? ''));
        $rec = is_array($sms['Recipients'][0] ?? null) ? $sms['Recipients'][0] : [];
        $recStr = '';

        foreach (['number', 'phoneNumber'] as $k) {
            if (isset($rec[$k]) && $rec[$k] !== '') {
                $recStr = $rec[$k] . ': ';

                break;
            }
        }

        return trim(($recStr . ($rec['status'] ?? '') . ' ' . (($rec['statusCode'] ?? ''))) . '; ' . $msg);
    }

    private function sendSmsViaGenericJson(string $url, string $smsApiKey, string $smsSender, string $to, string $message): void
    {
        $response = Http::timeout(15)
            ->asJson()
            ->post($url, [
                'api_key' => $smsApiKey,
                'to' => $to,
                'sender' => $smsSender,
                'message' => $message,
            ]);

        if (! $response->successful()) {
            Log::error('Failed to send SMS OTP', [
                'identifier' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw ValidationException::withMessages([
                'identifier' => ['Could not send SMS OTP. Please check SMS provider settings.'],
            ]);
        }
    }
}
