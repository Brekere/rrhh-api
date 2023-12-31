<?php

namespace App\Http\Controllers\Api;

use App\Models\Documento;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Documento\PutRequest;
use App\Http\Requests\Documento\StoreRequest;

class DocumentoController extends ApiController
{
    public function index()
    {
        return response()->json(Documento::paginate(5));
    }

    public function all()
    {
        return response()->json(Documento::get());
    }

    public function store(StoreRequest $request)
    {
        return response()->json(Documento::create($request->validated()));
    }

    public function show(Documento $documento)
    {
        return response()->json($documento);
    }

    public function update(PutRequest $request, Documento $documento)
    {
        $documento->update($request->validated());
        return response()->json($documento);
    }

    public function destroy(Documento $documento)
    {
        $documento->delete();
        return response()->json("ok");
    }
}