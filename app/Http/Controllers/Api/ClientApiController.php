<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::where('is_active', true)->orderBy('name');

        if ($request->filled('q')) {
            $like = '%'.$request->q.'%';
            $query->where(fn($q) => $q->where('name', 'like', $like)
                                      ->orWhere('code', 'like', $like)
                                      ->orWhere('email', 'like', $like));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return ClientResource::collection($query->paginate($perPage));
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client);
    }
}
