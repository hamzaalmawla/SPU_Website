<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ShellController extends Controller
{
    public function content(): View
    {
        return view('admin.shell.content');
    }

    public function settings(): View
    {
        return view('admin.shell.settings');
    }

    public function users(): View
    {
        return view('admin.shell.users');
    }
}
