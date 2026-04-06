<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Business $business)
    {
        return inertia('Clients/Index', [
            'business' => $business,
            'clients'  => Client::orderBy('name')->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function store(Request $request, Business $business)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $business->clients()->create($request->only('name', 'email', 'phone'));

        return back();
    }

    public function update(Request $request, Business $business, Client $client)
    {
        abort_if($client->business_id !== $business->id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $client->update($request->only('name', 'email', 'phone'));

        return back();
    }

    public function destroy(Business $business, Client $client)
    {
        abort_if($client->business_id !== $business->id, 403);

        $client->delete();

        return back();
    }
}
