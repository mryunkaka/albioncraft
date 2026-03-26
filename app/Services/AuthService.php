<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Session;
use RuntimeException;

final class AuthService
{
    private UserRepository $users;
    private PlanRepository $plans;

    public function __construct()
    {
        $db = Database::connection();
        $this->users = new UserRepository($db);
        $this->plans = new PlanRepository($db);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<string, string>, user_id?: int}
     */
    public function register(array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['password_confirmation'] ?? '');
        $referredByCode = strtoupper(trim((string) ($input['referral_code'] ?? '')));

        $errors = [];

        if ($username === '' || strlen($username) < 3) {
            $errors['username'] = 'Username minimal 3 karakter.';
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email tidak valid.';
        }
        if ($password === '' || strlen($password) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        }
        if ($password !== $confirmPassword) {
            $errors['password_confirmation'] = 'Konfirmasi password tidak sama.';
        }

        if ($this->users->findByUsername($username) !== null) {
            $errors['username'] = 'Username sudah dipakai.';
        }
        if ($this->users->findByEmail($email) !== null) {
            $errors['email'] = 'Email sudah dipakai.';
        }

        if ($referredByCode !== '' && $this->users->findByReferralCode($referredByCode) === null) {
            $errors['referral_code'] = 'Kode referral salah, mohon ketik ulang.';
        }

        $freePlanId = $this->plans->findIdByCode('FREE');
        if ($freePlanId === null) {
            $freePlanId = $this->plans->findFirstPlanId();
        }
        if ($freePlanId === null) {
            throw new RuntimeException('Data plan kosong. Jalankan SQL seed plans terlebih dahulu.');
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $this->users->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'referral_code' => $this->generateReferralCode(),
            'referred_by_code' => $referredByCode !== '' ? $referredByCode : null,
            'plan_id' => $freePlanId,
            'plan_expired_at' => null,
            'status' => 'ACTIVE',
        ]);

        if ($referredByCode !== '') {
            $referralService = new ReferralService();
            $referralService->processRegistrationReferral($id, $referredByCode);
        }

        return ['ok' => true, 'errors' => [], 'user_id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<string, string>}
     */
    public function login(array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        $errors = [];
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email tidak valid.';
        }
        if ($password === '') {
            $errors['password'] = 'Password wajib diisi.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || ! password_verify($password, (string) $user['password_hash'])) {
            return ['ok' => false, 'errors' => ['auth' => 'Email atau password salah.']];
        }

        if (($user['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            return ['ok' => false, 'errors' => ['auth' => 'Akun tidak aktif.']];
        }

        $plan = $this->plans->findById((int) $user['plan_id']);

        Session::regenerate();
        Session::put('auth', [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'plan_id' => (int) $user['plan_id'],
            'plan_code' => (string) ($plan['code'] ?? 'FREE'),
            'plan_name' => (string) ($plan['name'] ?? 'Free'),
            'plan_expired_at' => $user['plan_expired_at'] ?? null,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function logout(): void
    {
        Session::destroy();
        Session::start();
    }

    public function isLoggedIn(): bool
    {
        return Session::has('auth');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $auth = Session::get('auth');
        return is_array($auth) ? $auth : null;
    }

    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        } while ($this->users->findByReferralCode($code) !== null);

        return $code;
    }
}
