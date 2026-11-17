<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\LoginLockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Data Master > Akun (T33, SPEC §15). Akun pegawai SHD dibuat otomatis saat login pertama;
 * di sini hanya akun bersama yang bisa ditambah. Nonaktifkan = soft delete, bisa diaktifkan kembali.
 * Hanya untuk akun pegawai SHD (middleware employee.account).
 */
class AccountController extends Controller
{
    public function __construct(private AuthService $auth, private LoginLockout $lockout) {}

    public function index(): AnonymousResourceCollection
    {
        return AccountResource::collection(
            User::withTrashed()->orderByRaw('deleted_at IS NOT NULL')->orderBy('name')->get()
        );
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = User::create([
            ...$request->safe()->only(['username', 'name', 'department', 'email', 'password']),
            'source' => User::SOURCE_MANUAL,
        ]);

        $this->log($request, 'account.created', $account);

        return AccountResource::make($account)->response()->setStatusCode(201);
    }

    /** Ubah email akun mana pun (T40): tujuan OTP lupa password. */
    public function update(UpdateEmailRequest $request, User $account): AccountResource
    {
        $this->ensureActive($account);

        $account->update(['email' => $request->validated('email')]);
        $this->log($request, 'account.email_updated', $account);

        return AccountResource::make($account);
    }

    /** Ubah password akun bersama; sesi lain akun itu dicabut. Kunci akibat salah password ikut dibuka. */
    public function updatePassword(UpdateAccountPasswordRequest $request, User $account): AccountResource
    {
        $this->ensureActive($account);
        if ($account->isApi()) {
            throw ValidationException::withMessages(['account' => 'Akun pegawai SHD memakai Reset password.']);
        }

        $this->auth->setPassword($account, $request->validated('password'), $request->user());
        $this->lockout->clear($account->loginId());
        $this->log($request, 'account.password_updated', $account);

        return AccountResource::make($account);
    }

    /** Reset akun pegawai SHD: password kembali ke NIK, semua sesinya dicabut. */
    public function reset(Request $request, User $account): AccountResource
    {
        $this->ensureActive($account);
        if (! $account->isApi()) {
            throw ValidationException::withMessages(['account' => 'Akun bersama memakai Ubah password.']);
        }
        $this->ensureNotSelf($request, $account, 'Gunakan Ganti password untuk akun sendiri.');

        $this->auth->resetEmployeePassword($account);
        $this->lockout->clear($account->loginId());
        $this->log($request, 'account.reset', $account);

        return AccountResource::make($account);
    }

    public function destroy(Request $request, User $account): AccountResource
    {
        $this->ensureActive($account);
        $this->ensureNotSelf($request, $account, 'Tidak bisa menonaktifkan akun sendiri.');

        $account->tokens()->delete();
        $account->delete();
        $this->log($request, 'account.deactivated', $account);

        return AccountResource::make($account);
    }

    public function restore(Request $request, User $account): AccountResource
    {
        if (! $account->trashed()) {
            throw ValidationException::withMessages(['account' => 'Akun sudah aktif.']);
        }

        $account->restore();
        $this->log($request, 'account.restored', $account);

        return AccountResource::make($account);
    }

    private function ensureActive(User $account): void
    {
        if ($account->trashed()) {
            throw ValidationException::withMessages(['account' => 'Akun sudah dinonaktifkan.']);
        }
    }

    private function ensureNotSelf(Request $request, User $account, string $message): void
    {
        if ($request->user()->is($account)) {
            throw ValidationException::withMessages(['account' => $message]);
        }
    }

    private function log(Request $request, string $event, User $account): void
    {
        Log::channel('security')->info($event, ['actor_id' => $request->user()->id, 'account_id' => $account->id, 'account' => $account->loginId(), 'ip' => $request->ip()]);
    }
}
