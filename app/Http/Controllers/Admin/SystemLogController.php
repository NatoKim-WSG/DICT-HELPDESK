<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SystemLogController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function showUnlockForm()
    {
        return view('admin.system-logs.unlock');
    }

    public function unlock(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        if (! $user || ! $user->isShadow()) {
            abort(403);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return back()->withErrors([
                'password' => 'Incorrect password.',
            ]);
        }

        $request->session()->put(
            EnsureSystemLogsUnlocked::SESSION_KEY,
            now()->addMinutes(20)->toDateTimeString()
        );

        $this->systemLogs->record(
            'system_logs.unlock',
            'Unlocked the system log viewer.',
            [
                'category' => 'system',
                'target_type' => User::class,
                'target_id' => $user->id,
            ]
        );

        return redirect()->route('admin.system-logs.index');
    }

    public function lock(Request $request)
    {
        $request->session()->forget(EnsureSystemLogsUnlocked::SESSION_KEY);

        $this->systemLogs->record(
            'system_logs.lock',
            'Locked the system log viewer.',
            [
                'category' => 'system',
                'target_type' => User::class,
                'target_id' => optional($request->user())->id,
            ]
        );

        return redirect()->route('admin.dashboard')
            ->with('success', 'System logs have been locked.');
    }

    public function index(Request $request)
    {
        $query = SystemLog::query()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('event')) {
            $query->where('event_type', $request->string('event')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', '%'.$search.'%')
                    ->orWhere('event_type', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $logs = $query->paginate(40)->withQueryString();
        $categories = SystemLog::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $events = SystemLog::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        return view('admin.system-logs.index', compact('logs', 'categories', 'events'));
    }
}
