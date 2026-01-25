<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,danger',
        ]);

        DB::table('system_notifications')->insert([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'System notification broadcasted successfully.');
    }

    public function destroy($id)
    {
        DB::table('system_notifications')->where('id', $id)->update(['is_active' => false]);

        return back()->with('success', 'Notification deactivated.');
    }
}
