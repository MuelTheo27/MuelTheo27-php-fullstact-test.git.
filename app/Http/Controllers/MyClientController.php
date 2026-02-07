<?php

namespace App\Http\Controllers;

use App\Models\MyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MyClientController extends Controller
{
    //
    private function redis($id)
    {
        return "my_client:{$id}";
    }

    private function generateRedisKey(MyClient $client)
    {
        Cache::put(
            $this->redis($client->id),
            $client->toJson(),
            now()->addDays(1)
        );
    }
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:250',
            'client_logo' => 'required',
            'client_prefix' => 'required'
        ]);

        $data['slug'] = Str::slug($validatedData['name']);
        $data['created_at'] = now();

        if ($request->hasFile('client_logo')) {
            $path = Storage::disk('s3')->put('clients', $request->file('client_logo'));
            $data['client_logo'] = Storage::disk('s3')->url($path);
        }

        $client = MyClient::create($data);

        $this->generateRedisKey($client);
        return response()->json($client);
    }

    public function update(Request $request, $id)
    {
        $client = MyClient::whereNull('deleted_at')->findOrFail($id);

        $data = $request([
            'name',
            'client_prefix',
            'is_project',
            'self_capture',
            'client_prefix',
            'address',
            'phone_number',
            'city'
        ]);
        if ($request->hasFile('client_logo')) {
            $path = Storage::disk('s3')->put('clients', $request->file('client_logo'));
            $data['client_logo'] = Storage::disk('s3')->url($path);

            $data['updated_at'] = now();
            $client->update($data);

            Cache::forget($this->generateRedisKey($id));
            $this->generateRedisKey($client);

            return response()->json($client);
        }
    }

    public function destroy($id){
        $client = MyClient::findOrFail($id);

        $client->update([
            'deleted_at' => now()
        ]);

        Cache::forget($this->generateRedisKey($id));

        return response()->json([
            'message' => 'Client Deleted'
        ]);
    }
}
