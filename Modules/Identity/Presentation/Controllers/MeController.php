<?php

namespace Modules\Identity\Presentation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Identity\Presentation\Resources\UserResource;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['position', 'workLocation']));
    }
}
