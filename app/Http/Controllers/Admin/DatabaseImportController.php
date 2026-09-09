<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseImportException;
use App\Services\SqliteDatabaseImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class DatabaseImportController extends Controller
{
    public function __invoke(Request $request, SqliteDatabaseImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'database' => ['required', 'file', 'extensions:sqlite', 'max:51200'],
        ], [
            'database.required' => __('Please select a SQLite database file.'),
            'database.extensions' => __('Please select a file with a .sqlite extension.'),
            'database.max' => __('The database file may not be larger than 50 MB.'),
        ]);

        try {
            $importer->import($validated['database']);
        } catch (DatabaseImportException $exception) {
            return back()->withErrors(['database' => $exception->getMessage()]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(config('session.cookie')));
        Cookie::queue(cookie('database_import_status', 'success', 5));

        return redirect()->route('login');
    }
}
