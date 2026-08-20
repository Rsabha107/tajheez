<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

abstract class Controller
{
    //
    public function Login(Request $request): Response
    {
        return Inertia::render('Auth/Login');
    }

}
