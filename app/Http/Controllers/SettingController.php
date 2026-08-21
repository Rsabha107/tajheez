<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function updateApprovalsEnabled(Request $request)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        Setting::set('approvals_enabled', $data['enabled'] ? '1' : '0');
        $request->session()->put('approvals_enabled', $data['enabled']);

        return response()->json(['approvalsEnabled' => $data['enabled']]);
    }

    public function updateShowItemValues(Request $request)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        Setting::set('show_item_values', $data['enabled'] ? '1' : '0');
        $request->session()->put('show_item_values', $data['enabled']);

        return response()->json(['showItemValues' => $data['enabled']]);
    }
}
